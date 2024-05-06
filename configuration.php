<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

/** @var \Icinga\Application\Modules\Module $this */

$section = $this->menuSection(N_('Certificate Monitoring'), array(
    'icon'      => 'check',
    'url'       => 'x509/dashboard',
    'priority'  => 40
));

$section->add(N_('Certificate Overview'), array(
    'url'       => 'x509/certificates',
    'priority'  => 10
));

$section->add(N_('Certificate Usage'), array(
    'url'       => 'x509/usage',
    'priority'  => 20
));

$section->add(N_('Configuration'), [
    'url'         => 'x509/jobs',
    'priority'    => 100,
    'description' => $this->translate('Configure the scan jobs and SNI map')
]);

$this->provideConfigTab('backend', array(
    'title' => $this->translate('Configure the database backend'),
    'label' => $this->translate('Backend'),
    'url' => 'config/backend'
));

$this->providePermission('config/x509',
    $this->translate('allow to create/update jobs/schedules/snis if permission config/* is missing')
);
