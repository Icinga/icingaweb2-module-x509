<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
