<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

namespace Icinga\Module\X509\Forms\Config;

use Icinga\Data\ResourceFactory;
use Icinga\Forms\ConfigForm;

class BackendConfigForm extends ConfigForm
{
    public function init()
    {
        $this->setName('x509_backend');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $dbResources = ResourceFactory::getResourceConfigs('db')->keys();

        $this->addElement('select', 'backend_resource', [
            'label'         => $this->translate('Database'),
            'description'   => $this->translate('Database resource'),
            'multiOptions'  => array_combine($dbResources, $dbResources),
            'required'      => true
        ]);
    }
}
