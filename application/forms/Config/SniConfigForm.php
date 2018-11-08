<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Forms\Config;

use Icinga\Data\Filter\Filter;
use Icinga\Forms\RepositoryForm;

/**
 * Create, update and delete jobs
 */
class SniConfigForm extends RepositoryForm
{
    protected function createInsertElements(array $formData)
    {
        $this->addElements([
            [
                'text',
                'ip',
                [
                    'description'   => $this->translate('IP'),
                    'label'         => $this->translate('IP'),
                    'required'      => true
                ]
            ],
            [
                'textarea',
                'hostnames',
                [
                    'description'   => $this->translate('Comma-separated list of hostnames'),
                    'label'         => $this->translate('Hostnames'),
                    'required'      => true
                ]
            ]
        ]);

        $this->setTitle($this->translate('Create a new map'));
        $this->setSubmitLabel($this->translate('Create'));
    }

    protected function createUpdateElements(array $formData)
    {
        $this->createInsertElements($formData);
        $this->setTitle(sprintf($this->translate('Edit map for %s'), $this->getIdentifier()));
        $this->setSubmitLabel($this->translate('Save'));
    }

    protected function createDeleteElements(array $formData)
    {
        $this->setTitle(sprintf($this->translate('Remove map for %s?'), $this->getIdentifier()));
        $this->setSubmitLabel($this->translate('Yes'));
    }

    protected function createFilter()
    {
        return Filter::where('ip', $this->getIdentifier());
    }

    protected function getInsertMessage($success)
    {
        return $success
            ? $this->translate('Map created')
            : $this->translate('Failed to create map');
    }

    protected function getUpdateMessage($success)
    {
        return $success
            ? $this->translate('Map updated')
            : $this->translate('Failed to update map');
    }

    protected function getDeleteMessage($success)
    {
        return $success
            ? $this->translate('Map removed')
            : $this->translate('Failed to remove map');
    }
}
