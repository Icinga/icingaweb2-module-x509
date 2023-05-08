<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Forms\Config\JobConfigForm;
use Icinga\Module\X509\JobsIniRepository;
use Icinga\Web\Url;
use ipl\Html\HtmlElement;
use ipl\Scheduler\Contract\Frequency;
use ipl\Scheduler\Cron;
use ipl\Web\Compat\CompatController;

class JobsController extends CompatController
{
    protected function prepareInit()
    {
        parent::prepareInit();

        $this->getTabs()->disableLegacyExtensions();
    }

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
        $form = $this->prepareForm(true);

        $this->addTitleTab($this->translate('New Job'));
        $this->addContent($form);
    }

    /**
     * Update a job
     */
    public function updateAction()
    {
        $name = $this->params->getRequired('name');
        $form = $this->prepareForm();

        $this->addTitleTab($this->translate('Update Job'));

        $this->addContent($form);
    }

    /**
     * Remove a job
     */
    public function removeAction()
    {
        $name = $this->params->getRequired('name');
        $form = $this->prepareForm();

        $this->addTitleTab($this->translate('Remove Job'));

        $form->prependHtml(HtmlElement::create('h1', null, sprintf($this->translate('Remove job %s'), $name)));
        $this->addContent($form);
    }

    /**
     * Assert config permission and return a prepared RepositoryForm
     *
     * @return  JobConfigForm
     */
    protected function prepareForm(bool $isNew = false)
    {
        $this->assertPermission('config/x509');

        $repo = new JobsIniRepository();
        $form = (new JobConfigForm())
            ->setRedirectUrl(Url::fromPath('x509/jobs'))
            ->setRepo($repo);

        $values = [];
        if (! $isNew) {
            $name = $this->params->getRequired('name');
            $query = $repo->select()->where('name', $name);

            if (! $query->hasResult()) {
                $this->httpNotFound($this->translate('Job not found'));
            }

            $data = $query->fetchRow();
            if (! isset($data->frequencyType) && ! empty($data->schedule)) {
                $frequency = new Cron($data->schedule);
            } elseif (! empty($data->schedule)) {
                /** @var Frequency $type */
                $type = $data->frequencyType;
                $frequency = $type::fromJson($data->schedule);
            }

            $values = [
                'name'             => $data->name,
                'cidrs'            => $data->cidrs,
                'ports'            => $data->ports,
                'exclude_targets'  => $data->exclude_targets,
                'schedule-element' => $frequency ?? []
            ];
        }

        $form
            ->populate($values)
            ->on(JobConfigForm::ON_SUCCESS, function () {
                $this->redirectNow(Url::fromPath('x509/jobs'));
            })
            ->handleRequest($this->getServerRequest());

        $parts = $form->getPartUpdates();
        if (! empty($parts)) {
            $this->sendMultipartUpdate(...$parts);
        }

        return $form;
    }
}
