<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config\Ipranges;

use Icinga\Forms\ConfirmRemovalForm;

class RemoveForm extends ConfirmRemovalForm
{
    use IprangesFormTrait;

    public function onSuccess()
    {
        $this->iprangesConfig
            ->removeSection($this->currentCidr)
            ->saveIni();
    }
}
