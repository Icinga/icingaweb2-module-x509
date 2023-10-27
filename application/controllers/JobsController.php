<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Forms\Jobs\JobConfigForm;
use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\Widget\Jobs;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class JobsController extends CompatController
{
    use Database;

    /**
     * List all jobs
     */
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Jobs'));
        $this->getTabs()->add('sni', [
            'title'      => $this->translate('Configure SNI'),
            'label'      => $this->translate('SNI'),
            'url'        => 'x509/sni',
            'baseTarget' => '_main'
        ]);

        $jobs = X509Job::on($this->getDb());
        if ($this->hasPermission('config/x509')) {
            $this->addControl(
                (new ButtonLink($this->translate('New Job'), Url::fromPath('x509/jobs/new'), 'plus'))
                    ->openInModal()
            );
        }

        $sortControl = $this->createSortControl($jobs, [
            'name'   => $this->translate('Name'),
            'author' => $this->translate('Author'),
            'ctime'  => $this->translate('Date Created'),
            'mtime'  => $this->translate('Date Modified')
        ]);

        $this->controls->getAttributes()->add('class', 'default-layout');
        $this->addControl($sortControl);

        $this->addContent(new Jobs($jobs));
    }

    public function newAction()
    {
        $this->assertPermission('config/x509');

        $this->addTitleTab($this->translate('New Job'));

        $form = (new JobConfigForm())
            ->setAction((string) Url::fromRequest())
            ->on(JobConfigForm::ON_SUCCESS, function () {
                $this->closeModalAndRefreshRelatedView(Url::fromPath('x509/jobs'));
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }
}
