<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

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
