<?php

namespace Icinga\Module\X509\Controllers;

use Icinga\Web\Controller;

class IconsController extends Controller
{
    /**
     * Disable layout rendering as this controller doesn't provide any html layouts
     */
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
    }

    public function indexAction()
    {
        $file = realpath(
            $this->Module()->getBaseDir() . '/public/font/icons.' . $this->params->get('q', 'svg')
        );

        if ($file === false) {
            $this->httpNotFound('File does not exist');
        }

        readfile($file);
    }
}
