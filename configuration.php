<?php

/** @var \Icinga\Application\Modules\Module $this */

$this->provideConfigTab('backend', array(
    'title' => $this->translate('Configure the database backend'),
    'label' => $this->translate('Backend'),
    'url' => 'config/backend'
));

$this->provideConfigTab('jobs', array(
    'title' => $this->translate('Configure the scan jobs'),
    'label' => $this->translate('Jobs'),
    'url' => 'jobs'
));

$this->provideCssFile('icons.css');
