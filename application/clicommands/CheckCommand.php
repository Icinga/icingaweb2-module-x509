<?php

// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509Target;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;

class CheckCommand extends Command
{
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

        $targets = X509Target::on(Database::get())
            ->with(['chain', 'chain.certificate'])
            ->without('target.chain.certificate.issuer_certificate');

        $targets->columns([
            'port',
            'chain.valid',
            'chain.invalid_reason',
            'subject'     => 'chain.certificate.subject',
            'self_signed' => 'chain.certificate.self_signed'
        ]);

        // Sub query for `valid_from` column
        $validFrom = $targets->createSubQuery(new X509Certificate(), 'chain.certificate');
        $validFrom
            ->columns([new Expression('MAX(%s)', ['valid_from'])])
            ->getSelectBase()
            ->resetWhere()
            ->where(new Expression('sub_certificate_link.certificate_chain_id = target_chain.id'));

        // Sub query for `valid_to` column
        $validTo = $targets->createSubQuery(new X509Certificate(), 'chain.certificate');
        $validTo
            ->columns([new Expression('MIN(%s)', ['valid_to'])])
            ->getSelectBase()
            // Reset the where clause generated within the createSubQuery() method.
            ->resetWhere()
            ->where(new Expression('sub_certificate_link.certificate_chain_id = target_chain.id'));

        list($validFromSelect, $_) = $validFrom->dump();
        list($validToSelect, $_) = $validTo->dump();
        $targets
            ->withColumns([
                'valid_from' => new Expression($validFromSelect),
                'valid_to'   => new Expression($validToSelect)
            ])
            ->getSelectBase()
            ->where(new Expression('target_chain_link.order = 0'));

        if ($ip !== null) {
            $targets->filter(Filter::equal('ip', $ip));
        }
        if ($hostname !== null) {
            $targets->filter(Filter::equal('hostname', $hostname));
        }
        if ($this->params->has('port')) {
            $targets->filter(Filter::equal('port', $this->params->get('port')));
        }

        $allowSelfSigned = (bool) $this->params->get('allow-self-signed', false);
        $warningThreshold = $this->splitThreshold($this->params->get('warning', '25%'));
        $criticalThreshold = $this->splitThreshold($this->params->get('critical', '10%'));

        $output = [];
        $perfData = [];

        $state = 3;
        foreach ($targets as $target) {
            if (! $target->chain->valid && (! $target['self_signed'] || ! $allowSelfSigned)) {
                $invalidMessage = $target['subject'] . ': ' . $target->chain->invalid_reason;
                $output[$invalidMessage] = $invalidMessage;
                $state = 2;
            }

            $now = new DateTime();
            $validFrom = DateTime::createFromFormat('U.u', sprintf('%F', $target->valid_from / 1000.0));
            $validTo = DateTime::createFromFormat('U.u', sprintf('%F', $target->valid_to / 1000.0));
            $criticalAfter = $this->thresholdToDateTime($validFrom, $validTo, $criticalThreshold);
            $warningAfter = $this->thresholdToDateTime($validFrom, $validTo, $warningThreshold);

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
                    : $validTo->getTimestamp() - time(),
                $validTo->getTimestamp() - $warningAfter->getTimestamp(),
                $validTo->getTimestamp() - $criticalAfter->getTimestamp(),
                $validTo->getTimestamp() - $validFrom->getTimestamp()
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
     * @param string $threshold
     *
     * @return int|DateInterval
     */
    protected function splitThreshold(string $threshold)
    {
        $match = preg_match('/(\d+)([%\w]{1})/', $threshold, $matches);
        if (! $match) {
            Logger::error('Invalid threshold definition: %s', $threshold);
            exit(3);
        }

        switch ($matches[2]) {
            case '%':
                return (int) $matches[1];
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

        return new DateInterval($intervalSpec);
    }

    /**
     * Convert the given threshold information to a DateTime object
     *
     * @param   DateTime           $from
     * @param   DateTime           $to
     * @param   int|DateInterval   $thresholdValue
     *
     * @return DateTimeInterface
     */
    protected function thresholdToDateTime(DateTime $from, DateTime $to, $thresholdValue): DateTimeInterface
    {
        $to = clone $to;
        if ($thresholdValue instanceof DateInterval) {
            return $to->sub($thresholdValue);
        }

        $coveredDays = (int) round($from->diff($to)->days * ($thresholdValue / 100));
        return $to->sub(new DateInterval('P' . $coveredDays . 'D'));
    }
}
