<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config;

use Icinga\Application\Config;
use Icinga\Forms\ConfigForm;

class BackendForm extends ConfigForm
{
    public function init()
    {
        $this->setName('x509_backend');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData)
    {
        $resources = ['' => ''];
        foreach (Config::app('resources') as $name => $resource) {
            if ($resource->type === 'db') {
                $resources[$name] = $name;
            }
        }

        ksort($resources);

        $this->addElement('select', 'backend_resource', [
            'label'         => $this->translate('Database'),
            'description'   => $this->translate('Database resource'),
            'required'      => true,
            'multiOptions'  => $resources
        ]);
    }
}
