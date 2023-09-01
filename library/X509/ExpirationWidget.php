<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Date\DateFormatter;
use Icinga\Util\Csp;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Web\Style;

class ExpirationWidget extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'expiration-widget'];

    protected $from;

    protected $to;

    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    protected function assemble()
    {
        $now = time();

        $from = $this->from;

        if ($from->getTimestamp() > $now) {
            $ratio = 0;
            $dateTip = $from->format('Y-m-d H:i:s');
            $message = sprintf(mt('x509', 'not until after %s'), DateFormatter::timeUntil($from->getTimestamp(), true));
        } else {
            $to = $this->to;

            $secondsRemaining = $to->getTimestamp() - $now;
            $daysRemaining = ($secondsRemaining - $secondsRemaining % 86400) / 86400;
            if ($daysRemaining > 0) {
                $secondsTotal = $to->getTimestamp() - $from->getTimestamp();
                $daysTotal = ($secondsTotal - $secondsTotal % 86400) / 86400;

                $ratio = min(100, 100 - round(($daysRemaining * 100) / $daysTotal, 2));
                $message = sprintf(mt('x509', 'in %d days'), $daysRemaining);
            } else {
                $ratio = 100;
                if ($daysRemaining < 0) {
                    $message = sprintf(mt('x509', '%d days ago'), $daysRemaining * -1);
                } else {
                    $message = mt('x509', 'today');
                }
            }

            $dateTip = $to->format('Y-m-d H:i:s');
        }

        if ($ratio >= 75) {
            if ($ratio >= 90) {
                $state = 'state-critical';
            } else {
                $state = 'state-warning';
            }
        } else {
            $state = 'state-ok';
        }

        $progressBar = Html::tag('div', ['class' => "bg-stateful $state"], new HtmlString('&nbsp;'));
        $progressBarStyle = (new Style())
            ->setModule('x509')
            ->setNonce(Csp::getStyleNonce())
            ->addFor($progressBar, ['width' => sprintf('%F%%', $ratio)]);

        $this->addHtml(Html::tag('span', ['class' => 'progress-bar-label', 'title' => $dateTip], $message));
        $this->addHtml($progressBarStyle, Html::tag('div', ['class' => 'progress-bar dont-print'], $progressBar));
    }
}
