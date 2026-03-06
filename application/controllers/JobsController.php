<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Forms\Jobs\JobConfigForm;
use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\Widget\Jobs;
use ipl\Html\Contract\Form;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;

class JobsController extends CompatController
{
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

        $jobs = X509Job::on(Database::get());
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
            ->on(Form::ON_SUBMIT, function () {
                $this->closeModalAndRefreshRelatedView(Url::fromPath('x509/jobs'));
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }
}
