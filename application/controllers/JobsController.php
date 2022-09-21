<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\X509\Forms\Config\JobConfigForm;
use Icinga\Module\X509\JobsIniRepository;
use Icinga\Web\Controller;
use Icinga\Web\Url;

class JobsController extends Controller
{
    /**
     * List all jobs
     */
    public function indexAction()
    {
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('jobs');

        $repo = new JobsIniRepository();

        $this->view->jobs = $repo->select(array('name'));
    }

    /**
     * Create a job
     */
    public function newAction()
    {
        $form = $this->prepareForm()->add();

        $form->handleRequest();

        $this->renderForm($form, $this->translate('New Job'));
    }

    /**
     * Update a job
     */
    public function updateAction()
    {
        $form = $this->prepareForm()->edit($this->params->getRequired('name'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('Job not found'));
        }

        $this->renderForm($form, $this->translate('Update Job'));
    }

    /**
     * Remove a job
     */
    public function removeAction()
    {
        $form = $this->prepareForm()->remove($this->params->getRequired('name'));

        try {
            $form->handleRequest();
        } catch (NotFoundError $_) {
            $this->httpNotFound($this->translate('Job not found'));
        }

        $this->renderForm($form, $this->translate('Remove Job'));
    }

    /**
     * Assert config permission and return a prepared RepositoryForm
     *
     * @return  JobConfigForm
     */
    protected function prepareForm()
    {
        $this->assertPermission('config/x509');

        return (new JobConfigForm())
            ->setRepository(new JobsIniRepository())
            ->setRedirectUrl(Url::fromPath('x509/jobs'));
    }
}
