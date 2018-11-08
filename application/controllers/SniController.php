<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\X509\Forms\Config\SniConfigForm;
use Icinga\Module\X509\SniIniRepository;
use Icinga\Web\Controller;
use Icinga\Web\Url;

class SniController extends Controller
{
    /**
     * List all maps
     */
    public function indexAction()
    {
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('sni');

        $repo = new SniIniRepository();

        $this->view->sni = $repo->select(array('ip'));
    }

    /**
     * Create a map
     */
    public function newAction()
    {
        $form = $this->prepareForm()->add();

        $form->handleRequest();

        $this->renderForm($form, $this->translate('New SNI Map'));
    }

    /**
     * Update a map
     */
    public function updateAction()
    {
        $form = $this->prepareForm()->edit($this->params->getRequired('ip'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('IP not found'));
        }

        $this->renderForm($form, $this->translate('Update SNI Map'));
    }

    /**
     * Remove a map
     */
    public function removeAction()
    {
        $form = $this->prepareForm()->remove($this->params->getRequired('ip'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('IP not found'));
        }

        $this->renderForm($form, $this->translate('Remove SNI Map'));
    }

    /**
     * Assert config permission and return a prepared RepositoryForm
     *
     * @return  SniConfigForm
     */
    protected function prepareForm()
    {
        $this->assertPermission('config/x509');

        return (new SniConfigForm())
            ->setRepository(new SniIniRepository())
            ->setRedirectUrl(Url::fromPath('x509/sni'));
    }
}
