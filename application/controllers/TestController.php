<?php


namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Forms\Config\GenerateScheduleForm;
use ipl\Web\Compat\CompatController;


class TestController extends CompatController
{
    public function indexAction()
    {
        $this->setTitle('just for test');

        $enerateForm =  new GenerateScheduleForm();
        $this->addContent($enerateForm);
    }

}
