<?php

namespace Icinga\Module\X509;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Translation\Translation;

class ExpirationWidget extends BaseHtmlElement
{
    use Translation;

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
            return $this->translate('Not started');
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
            Html::content('span', (new \DateTime())->setTimestamp($to)->format('Y-m-d')),
            Html::tag(
                'span',
                ['class' => 'certificate-days-remaining'],
                sprintf($this->translate('%d days remaining'), $daysRemaining)
            ),
            Html::tag(
                'div',
                ['class' => 'progress-bar'],
                Html::tag(
                    'div',
                    ['style' => "width: {$ratio}%;", 'class' => "bg-stateful {$state}"],
                    new Text('&nbsp;', true)
                )
            )
        ]);
    }
}
