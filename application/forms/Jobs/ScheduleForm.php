<?php

/* Icinga Web 2 X.509 Module | (c) 2023 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Forms\Jobs;

use DateTime;
use Exception;
use Icinga\Application\Icinga;
use Icinga\Application\Web;
use Icinga\Authentication\Auth;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Model\X509Schedule;
use Icinga\User;
use Icinga\Util\Json;
use Icinga\Web\Notification;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Validator\CallbackValidator;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\ScheduleElement;
use Psr\Http\Message\RequestInterface;

use function ipl\Stdlib\get_php_type;

class ScheduleForm extends CompatForm
{
    use Database;

    /** @var int */
    protected $jobId;

    /** @var ?X509Schedule */
    protected $schedule;

    /** @var ScheduleElement */
    protected $scheduleElement;

    public function __construct(X509Schedule $schedule = null)
    {
        $this->schedule = $schedule;
        $this->scheduleElement = new ScheduleElement('schedule_element');

        /** @var Web $app */
        $app = Icinga::app();
        $this->scheduleElement->setIdProtector([$app->getRequest(), 'protectId']);
    }

    protected function isUpdating(): bool
    {
        return $this->schedule !== null;
    }

    public function setJobId(int $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * Get multipart updates
     *
     * @return array<int, BaseHtmlElement>
     */
    public function getPartUpdates(): array
    {
        /** @var RequestInterface $request */
        $request = $this->getRequest();
        return $this->scheduleElement->prepareMultipartUpdate($request);
    }

    public function hasBeenSubmitted(): bool
    {
        if (! $this->hasBeenSent()) {
            return false;
        }

        $button = $this->getPressedSubmitElement();
        return $button && ($button->getName() === 'submit' || $button->getName() === 'btn_remove');
    }

    protected function assemble(): void
    {
        $this->addElement('text', 'name', [
            'required'    => true,
            'label'       => $this->translate('Name'),
            'description' => $this->translate('Schedule name'),
        ]);

        $this->addElement('checkbox', 'full_scan', [
            'required'    => false,
            'class'       => 'autosubmit',
            'label'       => $this->translate('Full Scan'),
            'description' => $this->translate(
                'Scan all known and unknown targets of this job. (Defaults to only scan unknown targets)'
            )
        ]);

        if ($this->getPopulatedValue('full_scan', 'n') === 'n') {
            $this->addElement('checkbox', 'rescan', [
                'required'    => false,
                'class'       => 'autosubmit',
                'label'       => $this->translate('Rescan'),
                'description' => $this->translate('Rescan only targets that have been scanned before')
            ]);

            $this->addElement('text', 'since_last_scan', [
                'required'    => false,
                'label'       => $this->translate('Since Last Scan'),
                'placeholder' => '-24 hours',
                'description' => $this->translate(
                    'Scan targets whose last scan is older than the specified date/time, which can also be an'
                    . ' English textual datetime description like "2 days". If you want to scan only unknown targets'
                    . ' you can set this to "null".'
                ),
                'validators'  => [
                    new CallbackValidator(function ($value, CallbackValidator $validator) {
                        if ($value !== null && $value !== 'null') {
                            try {
                                new DateTime($value);
                            } catch (Exception $_) {
                                $validator->addMessage($this->translate('Invalid textual date time'));

                                return false;
                            }
                        }

                        return true;
                    })
                ]
            ]);
        }

        $this->addHtml(HtmlElement::create('div', ['class' => 'schedule-element-separator']));
        $this->addElement($this->scheduleElement);

        $this->addElement('submit', 'submit', [
            'label' => $this->isUpdating() ? $this->translate('Update') : $this->translate('Schedule')
        ]);

        if ($this->isUpdating()) {
            $removeButton = $this->createElement('submit', 'btn_remove', [
                'class' => 'btn-remove',
                'label' => $this->translate('Remove Schedule'),
            ]);
            $this->registerElement($removeButton);

            /** @var HtmlDocument $wrapper */
            $wrapper = $this->getElement('submit')->getWrapper();
            $wrapper->prepend($removeButton);
        }
    }

    protected function onSuccess(): void
    {
        /** @var X509Schedule $schedule */
        $schedule = $this->schedule;
        $conn = $this->getDb();
        /** @var FormSubmitElement $submitElement */
        $submitElement = $this->getPressedSubmitElement();
        if ($submitElement->getName() === 'btn_remove') {
            $conn->delete('x509_schedule', ['id = ?' => $schedule->id]);

            Notification::success($this->translate('Deleted schedule successfully'));
        } else {
            $config = $this->getValues();
            unset($config['name']);
            unset($config['schedule_element']);

            $frequency = $this->scheduleElement->getValue();
            $config['type'] = get_php_type($frequency);
            $config['frequency'] = Json::encode($frequency);

            /** @var User $user */
            $user = Auth::getInstance()->getUser();
            if (! $this->isUpdating()) {
                $conn->insert('x509_schedule', [
                    'job_id' => $this->schedule ? $this->schedule->job_id : $this->jobId,
                    'name'   => $this->getValue('name'),
                    'author' => $user->getUsername(),
                    'config' => Json::encode($config),
                    'ctime'  => (new DateTime())->getTimestamp() * 1000.0,
                    'mtime'  => (new DateTime())->getTimestamp() * 1000.0
                ]);
                $message = $this->translate('Created schedule successfully');
            } else {
                $conn->update('x509_schedule', [
                    'name'   => $this->getValue('name'),
                    'config' => Json::encode($config),
                    'mtime'  => (new DateTime())->getTimestamp() * 1000.0
                ], ['id = ?' => $schedule->id]);
                $message = $this->translate('Updated schedule successfully');
            }

            Notification::success($message);
        }
    }
}
