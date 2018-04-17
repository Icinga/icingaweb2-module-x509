<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Forms\Config\Ipranges;

use Icinga\Application\Config;
use Icinga\Web\Form;

trait IprangesFormTrait
{
    /**
     * The IP and port ranges "repository"
     *
     * @var Config
     */
    protected $iprangesConfig;

    /**
     * The IP range to edit
     *
     * @var string
     */
    protected $currentCidr;

    /**
     * Add elements for prefix and bits
     */
    protected function createIpElements()
    {
        /** @var Form $this */

        $this->addElements([
            [
                'text',
                'job',
                [
                    'label'         => $this->translate('Job'),
                    'description'   => $this->translate('Job name'),
                    'required'      => true
                ]
            ],
            [
                'text',
                'prefix',
                [
                    'label'         => $this->translate('Prefix'),
                    'description'   => $this->translate('IP prefix'),
                    'required'      => true,
                    'validators'    => ['ip']
                ]
            ],
            [
                'number',
                'bits',
                [
                    'label'         => $this->translate('Bits'),
                    'description'   => $this->translate('Hostbits'),
                    'required'      => true,
                    'min'           => 0,
                    'max'           => 128
                ]
            ]
        ]);
    }

    /**
     * Normalize prefix and bits and assemble CIDR
     *
     * @return string
     */
    protected function getCidr()
    {
        /** @var Form $this */

        $prefix = $this->getValue('prefix');
        $bits = (int) $this->getValue('bits');

        if (strpos($prefix, ':') === false) {
            $prefix = "::ffff:$prefix";

            if ($bits <= 32) {
                $bits += 96;
            }
        }

        return inet_ntop(inet_pton($prefix)) . "/$bits";
    }

    /**
     * Set {@link iprangesConfig}
     *
     * @param Config $iprangesConfig
     *
     * @return $this
     */
    public function setIprangesConfig(Config $iprangesConfig)
    {
        $this->iprangesConfig = $iprangesConfig;

        return $this;
    }

    /**
     * Set {@link currentCidr}
     *
     * @param string $currentCidr
     *
     * @return $this
     */
    public function setCurrentCidr($currentCidr)
    {
        $this->currentCidr = $currentCidr;

        return $this;
    }
}
