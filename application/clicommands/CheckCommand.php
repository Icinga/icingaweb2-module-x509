<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\DbTool;
use Icinga\Module\X509\Job;
use ipl\Sql\Select;

class CheckCommand extends Command
{
    public const UNIT_PERCENT = 'percent';
    public const UNIT_INTERVAL = 'interval';

    /**
     * Check a host's certificate
     *
     * This command utilizes this module's database to check if the given host serves valid certificates.
     *
     * USAGE
     *
     * icingacli x509 check host [options]
     *
     * OPTIONS
     *
     * You can either pass --ip or --host or both at the same time but at least one is mandatory.
     *
     *   --ip                   A hosts IP address
     *   --host                 A hosts name
     *   --port                 The port to check in particular
     *   --warning              Less remaining time results in state WARNING
     *                          Default: 25%
     *   --critical             Less remaining time results in state CRITICAL
     *                          Default: 10%
     *   --allow-self-signed    Ignore if a certificate or its issuer has been
     *                          self-signed
     *
     * EXAMPLES
     *
     *   icingacli x509 check host --ip 10.0.10.78
     *   icingacli x509 check host --host mail.example.org
     *   icingacli x509 check host --ip 10.0.10.78 --host mail.example.org --port 993
     *
     * THRESHOLD DEFINITION
     *
     *   Thresholds can either be defined relative (in percent) or absolute
     *   (time interval). Time intervals consist of a digit and an accompanying
     *   unit (e.g. "3M" are three months). Supported units are:
     *
     *     Year: y, Y
     *     Month: M
     *     Day: d, D
     *     Hour: h, H
     *     Minute: m
     *     Second: s, S
     */
    public function hostAction()
    {
        $ip = $this->params->get('ip');
        $hostname = $this->params->get('host');
        if ($ip === null && $hostname === null) {
            $this->showUsage('host');
            exit(3);
        }

        $dbTool = new DbTool($this->getDb());
        $targets = (new Select())
            ->from('x509_target t')
            ->columns([
                't.port',
                'cc.valid',
                'cc.invalid_reason',
                'c.subject',
                'self_signed'   => 'COALESCE(ci.self_signed, c.self_signed)',
                'valid_from'    => (new Select())
                    ->from('x509_certificate_chain_link xccl')
                    ->columns('MAX(GREATEST(xc.valid_from, xci.valid_from))')
                    ->join('x509_certificate xc', 'xc.id = xccl.certificate_id')
                    ->join('x509_certificate xci', 'xci.subject_hash = xc.issuer_hash')
                    ->where('xccl.certificate_chain_id = cc.id'),
                'valid_to'      => (new Select())
                    ->from('x509_certificate_chain_link xccl')
                    ->columns('MIN(LEAST(xc.valid_to, xci.valid_to))')
                    ->join('x509_certificate xc', 'xc.id = xccl.certificate_id')
                    ->join('x509_certificate xci', 'xci.subject_hash = xc.issuer_hash')
                    ->where('xccl.certificate_chain_id = cc.id')
            ])
            ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->joinLeft('x509_certificate ci', 'ci.subject_hash = c.issuer_hash')
            ->where(['ccl.order = ?' => 0]);

        if ($ip !== null) {
            $targets->where(['t.ip = ?' => $dbTool->marshalBinary(Job::binary($ip))]);
        }
        if ($hostname !== null) {
            $targets->where(['t.hostname = ?' => $hostname]);
        }
        if ($this->params->has('port')) {
            $targets->where(['t.port = ?' => $this->params->get('port')]);
        }

        $allowSelfSigned = (bool) $this->params->get('allow-self-signed', false);
        list($warningThreshold, $warningUnit) = $this->splitThreshold($this->params->get('warning', '25%'));
        list($criticalThreshold, $criticalUnit) = $this->splitThreshold($this->params->get('critical', '10%'));

        $output = [];
        $perfData = [];

        $state = 3;
        foreach ($this->getDb()->select($targets) as $target) {
            if ($target->valid === 'no' && ($target->self_signed === 'no' || ! $allowSelfSigned)) {
                $invalidMessage = $target->subject . ': ' . $target->invalid_reason;
                $output[$invalidMessage] = $invalidMessage;
                $state = 2;
            }

            $now = new \DateTime();
            $validFrom = (new \DateTime())->setTimestamp($target->valid_from);
            $validTo = (new \DateTime())->setTimestamp($target->valid_to);
            $criticalAfter = $this->thresholdToDateTime($validFrom, $validTo, $criticalThreshold, $criticalUnit);
            $warningAfter = $this->thresholdToDateTime($validFrom, $validTo, $warningThreshold, $warningUnit);

            if ($now > $criticalAfter) {
                $state = 2;
            } elseif ($state !== 2 && $now > $warningAfter) {
                $state = 1;
            } elseif ($state === 3) {
                $state = 0;
            }

            $remainingTime = $now->diff($validTo);
            if (! $remainingTime->invert) {
                // The certificate has not expired yet
                $output[$target->subject] = sprintf(
                    '%s expires in %d days',
                    $target->subject,
                    $remainingTime->days
                );
            } else {
                $output[$target->subject] = sprintf(
                    '%s has expired since %d days',
                    $target->subject,
                    $remainingTime->days
                );
            }

            $perfData[$target->subject] = sprintf(
                "'%s'=%ds;%d:;%d:;0;%d",
                $target->subject,
                $remainingTime->invert
                    ? 0
                    : $target->valid_to - time(),
                $target->valid_to - $warningAfter->getTimestamp(),
                $target->valid_to - $criticalAfter->getTimestamp(),
                $target->valid_to - $target->valid_from
            );
        }

        echo ['OK', 'WARNING', 'CRITICAL', 'UNKNOWN'][$state];
        echo ' - ';

        if (! empty($output)) {
            echo join('; ', $output);
        } elseif ($state === 3) {
            echo 'Host not found';
        }

        if (! empty($perfData)) {
            echo '|' . join(' ', $perfData);
        }

        echo PHP_EOL;
        exit($state);
    }

    /**
     * Parse the given threshold definition
     *
     * @param   string  $threshold
     *
     * @return  array
     */
    protected function splitThreshold($threshold)
    {
        $match = preg_match('/(\d+)([%\w]{1})/', $threshold, $matches);
        if (! $match) {
            Logger::error('Invalid threshold definition: %s', $threshold);
            exit(3);
        }

        switch ($matches[2]) {
            case '%':
                return [(int) $matches[1], self::UNIT_PERCENT];
            case 'y':
            case 'Y':
                $intervalSpec = 'P' . $matches[1] . 'Y';
                break;
            case 'M':
                $intervalSpec = 'P' . $matches[1] . 'M';
                break;
            case 'd':
            case 'D':
                $intervalSpec = 'P' . $matches[1] . 'D';
                break;
            case 'h':
            case 'H':
                $intervalSpec = 'PT' . $matches[1] . 'H';
                break;
            case 'm':
                $intervalSpec = 'PT' . $matches[1] . 'M';
                break;
            case 's':
            case 'S':
                $intervalSpec = 'PT' . $matches[1] . 'S';
                break;
            default:
                Logger::error('Unknown threshold unit given: %s', $threshold);
                exit(3);
        }

        return [new \DateInterval($intervalSpec), self::UNIT_INTERVAL];
    }

    /**
     * Convert the given threshold information to a DateTime object
     *
     * @param   \DateTime           $from
     * @param   \DateTime           $to
     * @param   int|\DateInterval   $thresholdValue
     * @param   string              $thresholdUnit
     *
     * @return  \DateTime
     */
    protected function thresholdToDateTime(\DateTime $from, \DateTime $to, $thresholdValue, $thresholdUnit)
    {
        $to = clone $to;
        if ($thresholdUnit === self::UNIT_INTERVAL) {
            return $to->sub($thresholdValue);
        }

        $coveredDays = (int) round($from->diff($to)->days * ($thresholdValue / 100));
        return $to->sub(new \DateInterval('P' . $coveredDays . 'D'));
    }
}
