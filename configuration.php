<?php

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('backend', array(
    'title' => $this->translate('Configure the database backend'),
    'label' => $this->translate('Backend'),
    'url' => 'config/backend'
));
$this->provideConfigTab('ipranges', array(
    'title' => $this->translate('Configure the IP/port ranges'),
    'label' => $this->translate('IP Ranges'),
    'url' => 'ipranges'
));
