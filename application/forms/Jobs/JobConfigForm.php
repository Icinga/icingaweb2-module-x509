<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Forms\Jobs;

use DateTime;
use Exception;
use Icinga\Authentication\Auth;
use Icinga\Module\X509\Common\Database;
use Icinga\Module\X509\Model\X509Job;
use Icinga\User;
use Icinga\Web\Notification;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\HtmlDocument;
use ipl\Stdlib\Str;
use ipl\Validator\CallbackValidator;
use ipl\Validator\CidrValidator;
use ipl\Web\Compat\CompatForm;

/**
 * Create, update and delete jobs
 */
class JobConfigForm extends CompatForm
{
    use Database;

    /** @var ?X509Job */
    protected $job;

    public function __construct(X509Job $job = null)
    {
        $this->job = $job;
    }

    protected function isUpdating(): bool
    {
        return $this->job !== null;
    }

    public function hasBeenSubmitted(): bool
    {
        if (! $this->hasBeenSent()) {
            return false;
        }

        $button = $this->getPressedSubmitElement();

        return $button && ($button->getName() === 'btn_submit' || $button->getName() === 'btn_remove');
    }

    protected function assemble(): void
    {
        $this->addElement('text', 'name', [
            'required'    => true,
            'label'       => $this->translate('Name'),
            'description' => $this->translate('Job name'),
        ]);

        $this->addElement('textarea', 'cidrs', [
            'required'    => true,
            'label'       => $this->translate('CIDRs'),
            'description' => $this->translate('Comma-separated list of CIDR addresses to scan'),
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
            'label'       => $this->translate('Ports'),
            'description' => $this->translate('Comma-separated list of ports to scan'),
        ]);

        $this->addElement('textarea', 'exclude_targets', [
            'required'    => false,
            'label'       => $this->translate('Exclude Targets'),
            'description' => $this->translate('Comma-separated list of addresses/hostnames to exclude'),
        ]);

        $this->addElement('submit', 'btn_submit', [
            'label' => $this->isUpdating() ? $this->translate('Update') : $this->translate('Create')
        ]);

        if ($this->isUpdating()) {
            $removeButton = $this->createElement('submit', 'btn_remove', [
                'class' => 'btn-remove',
                'label' => $this->translate('Remove Job'),
            ]);
            $this->registerElement($removeButton);

            /** @var HtmlDocument $wrapper */
            $wrapper = $this->getElement('btn_submit')->getWrapper();
            $wrapper->prepend($removeButton);
        }
    }

    protected function onSuccess(): void
    {
        $conn = $this->getDb();
        /** @var FormSubmitElement $submitElement */
        $submitElement = $this->getPressedSubmitElement();
        if ($submitElement->getName() === 'btn_remove') {
            try {
                /** @var X509Job $job */
                $job = $this->job;
                $conn->delete('x509_job', ['id = ?' => $job->id]);

                Notification::success($this->translate('Removed job successfully'));
            } catch (Exception $err) {
                Notification::error($this->translate('Failed to remove job') . ': ' . $err->getMessage());
            }
        } else {
            $values = $this->getValues();

            try {
                /** @var User $user */
                $user = Auth::getInstance()->getUser();
                if ($this->job === null) {
                    $values['author'] = $user->getUsername();
                    $values['ctime'] = (new DateTime())->getTimestamp() * 1000.0;
                    $values['mtime'] = (new DateTime())->getTimestamp() * 1000.0;

                    $conn->insert('x509_job', $values);
                    $message = $this->translate('Created job successfully');
                } else {
                    $values['mtime'] = (new DateTime())->getTimestamp() * 1000.0;

                    $conn->update('x509_job', $values, ['id = ?' => $this->job->id]);
                    $message = $this->translate('Updated job successfully');
                }

                Notification::success($message);
            } catch (Exception $err) {
                $message = $this->isUpdating()
                    ? $this->translate('Failed to update job')
                    : $this->translate('Failed to create job');

                Notification::error($message . ': ' . $err->getMessage());
            }
        }
    }
}
