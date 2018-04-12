<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Controllers;

use Icinga\Application\Config;
use Icinga\Module\X509\Forms\Config\BackendForm;
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
        $form = new BackendForm();
        $form->setIniConfig(Config::module('x509'))
            ->handleRequest();

        $this->view->form = $form;
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('backend');
    }
}
