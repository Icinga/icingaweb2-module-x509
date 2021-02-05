<?php


namespace Icinga\Module\X509\Forms\Config;

use ipl\Html\Form;
use ipl\Html\FormDecorator\DivDecorator;
use ipl\Html\Html;

class GenerateScheduleForm extends Form
{
    protected $months = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    ];

    protected $days = ['Monday', 'tuesday', 'wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected $quickSchedule = [
        'Daily' => '',
        'Weekly' => '',
        'Monthly' => '',
        'Yearly' => '',
    ];



    protected function assemble()
    {
        $this->setDefaultElementDecorator(new DivDecorator());

        $this->add(Html::tag('h2', 'Quick Schedule'));

        foreach ($this->quickSchedule as $schedule => $value) {
            $this->addElement('checkbox', $schedule, [
                'label' => $schedule,
            ]);
        }

        $this->addElement('select', 'day', [
            'required' => true,
            'label' => 'Day of week',
            'options' => [null => 'Please choose'] + $this->days,
            'class' => 'autosubmit'
        ]);

        $values = $this->getValues();
        var_dump($values);
        if (isset($values['day'])) {
            $this->addElement('select', 'month', [
                'required' => true,
                'label' => 'month',
                'options' => [null => 'Please choose'] + $this->months,
                'class' => 'autosubmit'
            ]);
        }
    }
}
