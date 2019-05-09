<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Clicommands;

use Icinga\Application\Logger;
use Icinga\Module\X509\Command;
use Icinga\Module\X509\Job;
use ipl\Sql\Select;

class CheckCommand extends Command
{
    const UNIT_PERCENT = 'percent';
    const UNIT_INTERVAL = 'interval';

    public function hostAction()
    {
        $ip = $this->params->get('ip');
        if ($ip === null) {
            $hostname = $this->params->getRequired('host');
        }

        $targets = (new Select())
            ->from('x509_target t')
            ->columns([
                't.port',
                'cc.valid',
                'cc.invalid_reason',
                'c.subject',
                'ci.self_signed',
                'c.valid_from',
                'c.valid_to'
            ])
            ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->join('x509_certificate ci', 'ci.subject_hash = c.issuer_hash')
            ->where(['ccl.order = ?' => 0]);
        if (isset($hostname)) {
            $targets->where(['t.hostname = ?' => $hostname]);
        } else {
            $targets->where(['t.ip = ?' => Job::binary($ip)])
                ->groupBy(['t.ip', 't.port']); // We may have multiple rows due to SNI
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
            if ($target['valid'] === 'no' && ($target['self_signed'] === 'no' || ! $allowSelfSigned)) {
                $output[] = $target['subject'] . ': ' . $target['invalid_reason'];
                $state = 2;
            }

            $now = new \DateTime();
            $validFrom = (new \DateTime())->setTimestamp($target['valid_from']);
            $validTo = (new \DateTime())->setTimestamp($target['valid_to']);
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
                $output[$target['subject']] = sprintf(
                    '%s expires in %d days',
                    $target['subject'],
                    $remainingTime->days
                );
            }

            $maxDays = $validFrom->diff($validTo)->days;
            $perfData[] = sprintf(
                "'%s'=%d;%d;%d;0;%d",
                $target['subject'],
                $remainingTime->invert
                    ? $maxDays
                    : $validFrom->diff($now)->days,
                $validFrom->diff($warningAfter)->days,
                $validFrom->diff($criticalAfter)->days,
                $maxDays
            );
        }

        echo ['OK', 'WARNING', 'CRITICAL', 'UNKNOWN'][$state];
        echo ' - ';

        if (! empty($output)) {
            echo join('; ', $output);
        } else {
            echo 'Host not found';
        }

        if (! empty($perfData)) {
            echo '|' . join(' ', $perfData);
        }

        echo PHP_EOL;
        exit($state);
    }

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
