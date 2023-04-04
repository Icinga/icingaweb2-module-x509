<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Forms\Config;

use Icinga\Application\Icinga;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\StatementException;
use Icinga\Module\X509\JobsIniRepository;
use Icinga\Web\Notification;
use ipl\Html\HtmlElement;
use ipl\Scheduler\Contract\Frequency;
use ipl\Stdlib\Str;
use ipl\Validator\CallbackValidator;
use ipl\Validator\CidrValidator;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\ScheduleElement;
use ipl\Web\Url;

use function ipl\Stdlib\get_php_type;

/**
 * Create, update and delete jobs
 */
class JobConfigForm extends CompatForm
{
    /** @var string The name of this job  */
    protected $identifier;

    /** @var JobsIniRepository */
    protected $repo;

    /** @var ScheduleElement */
    protected $scheduleElement;

    public function __construct()
    {
        $this->identifier = Url::fromRequest()->getParam('name');
        $this->scheduleElement = new ScheduleElement('schedule-element');
        $this->scheduleElement->setIdProtector([Icinga::app()->getRequest(), 'protectId']);
    }

    public function setRepo(JobsIniRepository $repo): self
    {
        $this->repo = $repo;

        return $this;
    }

    protected function createFilter()
    {
        return Filter::where('name', $this->identifier);
    }

    protected function isUpdating(): bool
    {
        return Url::fromRequest()->getPath() === 'x509/jobs/update';
    }

    protected function isRemoving(): bool
    {
        return Url::fromRequest()->getPath() === 'x509/jobs/remove';
    }

    /**
     * Get multipart updates
     *
     * @return array
     */
    public function getPartUpdates(): array
    {
        if ($this->scheduleElement->getFrequency() === 'none') {
            // Workaround for https://github.com/Icinga/ipl-web/issues/130
            return [];
        }

        return $this->scheduleElement->prepareMultipartUpdate($this->getRequest());
    }

    protected function assemble()
    {
        if (! $this->isRemoving()) {
            $this->addElement('text', 'name', [
                'required'    => true,
                'description' => t('Job name'),
                'label'       => t('Name'),
            ]);
            $this->addElement('textarea', 'cidrs', [
                'description' => t('Comma-separated list of CIDR addresses to scan'),
                'label'       => t('CIDRs'),
                'required'    => true,
                'validators'  => [
                    new CallbackValidator(function ($value, CallbackValidator $validator): bool {
                        $cidrValidator = new CidrValidator();
                        $cidrs = Str::trimSplit($value);

                        foreach ($cidrs as $cidr) {
                            if (! $cidrValidator->isValid($cidr)) {
                                $validator->addMessage(...$cidrValidator->getMessages());

                                return false;
                            }
                        }

                        return true;
                    })
                ]
            ]);
            $this->addElement('textarea', 'ports', [
                'required'    => true,
                'description' => t('Comma-separated list of ports to scan'),
                'label'       => t('Ports'),
            ]);

            $this->addElement('textarea', 'exclude_targets', [
                'description' => $this->translate('Comma-separated list of addresses/hostnames to exclude'),
                'label'       => $this->translate('Exclude Targets'),
                'required'    => false
            ]);

            $this->addHtml(HtmlElement::create('div', ['class' => 'schedule-element-separator']));
            $this->addElement($this->scheduleElement);
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->isRemoving() ? t('Yes') : ($this->isUpdating() ? t('Update') : t('Create'))
        ]);
    }

    protected function onSuccess()
    {
        if ($this->isRemoving()) {
            try {
                $this->repo->delete($this->repo->getBaseTable(), $this->createFilter());

                Notification::success(t('Job removed'));
            } catch (StatementException $err) {
                Notification::error(t('Failed to remove job'));
            }
        } else {
            /** @var Frequency $frequency */
            $frequency = $this->scheduleElement->getValue();
            $data = [
                'name'            => $this->getValue('name'),
                'cidrs'           => $this->getValue('cidrs'),
                'ports'           => $this->getValue('ports'),
                'schedule'        => json_encode($frequency),
                'frequencyType'   => get_php_type($frequency),
            ];

            $excludes = $this->getValue('exclude_targets');
            if (! empty($excludes)) {
                $data['exclude_targets'] = $excludes;
            }

            try {
                if ($this->isUpdating()) {
                    $message = t('Job updated');
                    $this->repo->update($this->repo->getBaseTable(), $data, $this->createFilter());
                } else {
                    $message = t('Job created');
                    $this->repo->insert($this->repo->getBaseTable(), $data);
                }

                Notification::success($message);
            } catch (StatementException $err) {
                $message = $this->isUpdating() ? t('Failed to update job') : t('Failed to create job');

                Notification::error($message . ': ' . $err->getMessage());
            }
        }
    }
}
