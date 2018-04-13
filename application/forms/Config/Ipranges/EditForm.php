<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config\Ipranges;

use Icinga\Web\Form;

class EditForm extends Form
{
    use IprangesFormTrait;

    public function init()
    {
        $this->setName('x509_ipranges_edit');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $this->createIpElements();

        list($prefix, $bits) = explode('/', $this->currentCidr, 2);

        $this->getElement('prefix')->setValue($prefix);
        $this->getElement('bits')->setValue($bits);

        foreach ($this->iprangesConfig->getSection($this->currentCidr) as $start => $end) {
            $this->addElements([
                [
                    'number',
                    "start_{$start}",
                    [
                        'label'         => $this->translate('Start'),
                        'description'   => $this->translate('First port'),
                        'required'      => true,
                        'value'         => $start,
                        'min'           => 0,
                        'max'           => 65535
                    ]
                ],
                [
                    'number',
                    "end_{$start}",
                    [
                        'label'         => $this->translate('End'),
                        'description'   => $this->translate('Last port'),
                        'required'      => true,
                        'value'         => $end,
                        'min'           => 0,
                        'max'           => 65535
                    ]
                ],
                [
                    'checkbox',
                    "delete_{$start}",
                    [
                        'label'         => $this->translate('Delete'),
                        'description'   => $this->translate('Delete this range')
                    ]
                ]
            ]);

            $this->addDisplayGroup(["start_{$start}", "end_{$start}", "delete_{$start}"], "range_{$start}");
        }

        $this->addElements([
            [
                'number',
                'start_new',
                [
                    'label'         => $this->translate('Start'),
                    'description'   => $this->translate('First port'),
                    'min'           => 0,
                    'max'           => 65535
                ]
            ],
            [
                'number',
                'end_new',
                [
                    'label'         => $this->translate('End'),
                    'description'   => $this->translate('Last port'),
                    'min'           => 0,
                    'max'           => 65535
                ]
            ]
        ]);

        $this->addDisplayGroup(['start_new', 'end_new'], 'range_new');
    }

    public function onSuccess()
    {
        $portRanges = [];
        foreach ($this->getElements() as $element) {
            $matches = [];
            if (preg_match('/^(start|end|delete)_(.+)$/', $element->getName(), $matches)) {
                $portRanges[$matches[2]][$matches[1]] = $element->getValue();
            }
        }

        $ports = [];
        if ($portRanges['new'] !== ['start' => '', 'end' => '']) {
            if ($portRanges['new']['end'] === '') {
                $ports[] = [$portRanges['new']['start']];
            } elseif ($portRanges['new']['start'] === '') {
                $ports[] = [$portRanges['new']['end']];
            } else {
                $ports[] = range($portRanges['new']['start'], $portRanges['new']['end']);
            }
        }

        unset($portRanges['new']);

        foreach ($portRanges as $portRange) {
            if (! $portRange['delete']) {
                $ports[] = range($portRange['start'], $portRange['end']);
            }
        }

        $rangesByStart = [];
        $rangesByEnd = [];
        $ports = empty($ports) ? [] : array_flip(call_user_func_array('array_merge', $ports));
        ksort($ports);

        foreach ($ports as $port => $_) {
            $prev = $port - 1;

            if (isset($rangesByEnd[$prev])) {
                $start = $rangesByEnd[$prev];
                ++$rangesByStart[$start];
                unset($rangesByEnd[$prev]);
                $rangesByEnd[$port] = $start;
            } else {
                $rangesByStart[$port] = $port;
                $rangesByEnd[$port] = $port;
            }
        }

        $cidr = $this->getCidr();

        $this->iprangesConfig
            ->removeSection($this->currentCidr)
            ->setSection($cidr, $rangesByStart)
            ->saveIni();

        $this->getRedirectUrl()->setParam('cidr', $cidr);
    }
}
