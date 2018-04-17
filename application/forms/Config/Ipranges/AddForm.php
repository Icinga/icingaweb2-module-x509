<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config\Ipranges;

use Icinga\Web\Form;

class AddForm extends Form
{
    use IprangesFormTrait;

    public function init()
    {
        $this->setName('x509_ipranges_add');
        $this->setSubmitLabel($this->translate('Add'));
    }

    public function createElements(array $formData)
    {
        $this->createIpElements();
    }

    public function onSuccess()
    {
        $cidr = $this->getCidr();

        $opts = [ 'job' => $this->getElement('job')->getValue() ];

        if (! $this->iprangesConfig->hasSection($cidr)) {
            $this->iprangesConfig
                ->setSection($cidr, $opts)
                ->saveIni();
        }

        $this->getRedirectUrl()->setParam('cidr', $cidr);
    }
}
