<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Application\Config;
use Icinga\Module\X509\Forms\Config\BackendConfigForm;
use Icinga\Web\Controller;

class ConfigController extends Controller
{
    public function init()
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

    public function backendAction()
    {
        $form = (new BackendConfigForm())
            ->setIniConfig(Config::module('x509'));

        $form->handleRequest();

        $this->view->tabs = $this->Module()->getConfigTabs()->activate('backend');
        $this->view->form = $form;
    }
}
