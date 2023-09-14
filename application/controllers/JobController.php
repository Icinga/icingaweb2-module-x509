<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Common\Links;
use Icinga\Module\X509\Forms\Jobs\JobConfigForm;
use Icinga\Module\X509\Model\X509Job;
use Icinga\Module\X509\Model\X509Schedule;
use Icinga\Module\X509\Forms\Jobs\ScheduleForm;
use Icinga\Module\X509\Widget\JobDetails;
use Icinga\Module\X509\Widget\Schedules;
use Icinga\Util\Json;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\ValidHtml;
use ipl\Scheduler\Contract\Frequency;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ActionBar;
use ipl\Web\Widget\ActionLink;
use ipl\Web\Widget\ButtonLink;
use stdClass;

class JobController extends CompatController
{
    use Database;

    /** @var X509Job */
    protected $job;

    public function init()
    {
        parent::init();

        $this->getTabs()->disableLegacyExtensions();

        /** @var int $jobId */
        $jobId = $this->params->getRequired('id');

        /** @var X509Job $job */
        $job = X509Job::on($this->getDb())
            ->filter(Filter::equal('id', $jobId))
            ->first();

        if ($job === null) {
            $this->httpNotFound($this->translate('Job not found'));
        }

        $this->job = $job;
    }

    public function indexAction(): void
    {
        $this->assertPermission('config/x509');

        $this->initTabs();
        $this->getTabs()->activate('job-activities');

        $jobRuns = $this->job->job_run->with(['job', 'schedule']);

        $limitControl = $this->createLimitControl();
        $sortControl = $this->createSortControl($jobRuns, [
            'schedule.name'    => $this->translate('Schedule Name'),
            'schedule.author'  => $this->translate('Author'),
            'total_targets'    => $this->translate('Total Targets'),
            'finished_targets' => $this->translate('Finished Targets'),
            'start_time desc'  => $this->translate('Started At'),
            'end_time'         => $this->translate('Ended At')
        ]);

        $this->controls->getAttributes()->add('class', 'default-layout');
        $this->addControl($sortControl);
        $this->addControl($limitControl);
        $this->addControl($this->createActionBar());

        $this->addContent(new JobDetails($jobRuns));
    }

    public function updateAction(): void
    {
        $this->assertPermission('config/x509');

        $this->addTitleTab($this->translate('Update Job'));

        $form = (new JobConfigForm($this->job))
            ->setAction((string) Url::fromRequest())
            ->populate([
                'name'            => $this->job->name,
                'cidrs'           => $this->job->cidrs,
                'ports'           => $this->job->ports,
                'exclude_targets' => $this->job->exclude_targets
            ])
            ->on(JobConfigForm::ON_SUCCESS, function (JobConfigForm $form) {
                /** @var FormSubmitElement $button */
                $button = $form->getPressedSubmitElement();
                if ($button->getName() === 'btn_remove') {
                    $this->switchToSingleColumnLayout();
                } else {
                    $this->closeModalAndRefreshRelatedView(Links::job($this->job));
                }
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    public function schedulesAction(): void
    {
        $this->assertPermission('config/x509');

        $this->initTabs();
        $this->getTabs()->activate('schedules');

        $schedules = $this->job->schedule->with(['job']);

        $sortControl = $this->createSortControl($schedules, [
            'name'   => $this->translate('Name'),
            'author' => $this->translate('Author'),
            'ctime'  => $this->translate('Date Created'),
            'mtime'  => $this->translate('Date Modified')
        ]);

        $this->controls->getAttributes()->add('class', 'default-layout');
        $this->addControl(
            (new ButtonLink($this->translate('New Schedule'), Links::scheduleJob($this->job), 'plus'))
                ->openInModal()
        );
        $this->addControl($sortControl);

        $this->addContent(new Schedules($schedules));
    }

    public function scheduleAction(): void
    {
        $this->assertPermission('config/x509');

        $this->addTitleTab($this->translate('Schedule Job'));

        $form = (new ScheduleForm())
            ->setAction((string) Url::fromRequest())
            ->setJobId($this->job->id)
            ->on(JobConfigForm::ON_SUCCESS, function () {
                $this->redirectNow(Links::schedules($this->job));
            })
            ->handleRequest($this->getServerRequest());

        $parts = $form->getPartUpdates();
        if (! empty($parts)) {
            $this->sendMultipartUpdate(...$parts);
        }

        $this->addContent($form);
    }

    public function updateScheduleAction(): void
    {
        $this->assertPermission('config/x509');

        $this->addTitleTab($this->translate('Update Schedule'));

        /** @var int $id */
        $id = $this->params->getRequired('scheduleId');
        /** @var X509Schedule $schedule */
        $schedule = X509Schedule::on($this->getDb())
            ->filter(Filter::equal('id', $id))
            ->first();
        if ($schedule === null) {
            $this->httpNotFound($this->translate('Schedule not found'));
        }

        /** @var stdClass $config */
        $config = Json::decode($schedule->config);
        /** @var Frequency $type */
        $type = $config->type;
        $frequency = $type::fromJson($config->frequency);

        $form = (new ScheduleForm($schedule))
            ->setAction((string) Url::fromRequest())
            ->populate([
                'name'             => $schedule->name,
                'full_scan'        => $config->full_scan ?? 'n',
                'rescan'           => $config->rescan ?? 'n',
                'since_last_scan'  => $config->since_last_scan ?? null,
                'schedule_element' => $frequency
            ])
            ->on(JobConfigForm::ON_SUCCESS, function () {
                $this->redirectNow('__BACK__');
            })
            ->handleRequest($this->getServerRequest());

        $parts = $form->getPartUpdates();
        if (! empty($parts)) {
            $this->sendMultipartUpdate(...$parts);
        }

        $this->addContent($form);
    }

    protected function createActionBar(): ValidHtml
    {
        $actions = new ActionBar();
        $actions->addHtml(
            (new ActionLink($this->translate('Modify'), Links::updateJob($this->job), 'edit'))
                ->openInModal(),
            (new ActionLink($this->translate('Schedule'), Links::scheduleJob($this->job), 'calendar'))
                ->openInModal()
        );

        return $actions;
    }

    protected function initTabs(): void
    {
        $tabs = $this->getTabs();
        $tabs
            ->add('job-activities', [
                'label' => $this->translate('Job Activities'),
                'url'   => Links::job($this->job)
            ])
            ->add('schedules', [
                'label' => $this->translate('Schedules'),
                'url'   => Links::schedules($this->job)
            ]);
    }
}
