<?php
/* Icinga Web 2 X.509 Module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config;

use Icinga\Data\Filter\Filter;
use Icinga\Forms\RepositoryForm;

/**
 * Create, update and delete jobs
 */
class JobConfigForm extends RepositoryForm
{
    protected function createInsertElements(array $formData)
    {
        $this->addElements([
            [
                'text',
                'name',
                [
                    'description'   => $this->translate('Job name'),
                    'label'         => $this->translate('Name'),
                    'required'      => true
                ]
            ],
            [
                'textarea',
                'cidrs',
                [
                    'description'   => $this->translate('Comma-separated list of CIDR addresses to scan'),
                    'label'         => $this->translate('CIDRs'),
                    'required'      => true
                ]
            ],
            [
                'textarea',
                'ports',
                [
                    'description'   => $this->translate('Comma-separated list of ports to scan  '),
                    'label'         => $this->translate('Bits'),
                    'required'      => true
                ]
            ]
        ]);

        $this->setTitle($this->translate('Create a new job'));
        $this->setSubmitLabel($this->translate('Create'));
    }

    protected function createUpdateElements(array $formData)
    {
        $this->createInsertElements($formData);
        $this->setTitle(sprintf($this->translate('Edit job %s'), $this->getIdentifier()));
        $this->setSubmitLabel($this->translate('Save'));
    }

    protected function createDeleteElements(array $formData)
    {
        $this->setTitle(sprintf($this->translate('Remove job %s?'), $this->getIdentifier()));
        $this->setSubmitLabel($this->translate('Yes'));
    }

    protected function createFilter()
    {
        return Filter::where('name', $this->getIdentifier());
    }

    protected function getInsertMessage($success)
    {
        return $success
            ? $this->translate('Job created')
            : $this->translate('Failed to create job');
    }

    protected function getUpdateMessage($success)
    {
        return $success
            ? $this->translate('Job updated')
            : $this->translate('Failed to update job');
    }

    protected function getDeleteMessage($success)
    {
        return $success
            ? $this->translate('Job removed')
            : $this->translate('Failed to remove job');
    }
}
