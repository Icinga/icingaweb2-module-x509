<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlString;

class ExpirationWidget extends BaseHtmlElement
{
    protected $tag = 'div';

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

        if ($from > $now) {
            return mt('x509', 'Not started');
        }

        $to = $this->to;

        $secondsRemaining = $to - $now;
        $daysRemaining = ($secondsRemaining - $secondsRemaining % 86400) / 86400;
        $secondsTotal = $to - $from;
        $daysTotal = ($secondsTotal - $secondsTotal % 86400) / 86400;

        $ratio = min(100, 100 - round(($daysRemaining * 100) / $daysTotal, 2));

        if ($ratio >= 75) {
            if ($ratio >= 90) {
                $state = 'state-critical';
            } else {
                $state = 'state-warning';
            }
        } else {
            $state = 'state-ok';
        }

        $this->add([
            Html::tag(
                'span',
                ['class' => '', 'style' => 'font-size: 0.9em;'],
                sprintf(mt('x509', 'in %d days'), $daysRemaining)
            ),
            Html::tag(
                'div',
                ['class' => 'progress-bar dont-print'],
                Html::tag(
                    'div',
                    ['style' => "width: {$ratio}%;", 'class' => "bg-stateful {$state}"],
                    new HtmlString('&nbsp;')
                )
            )
        ]);
    }
}
