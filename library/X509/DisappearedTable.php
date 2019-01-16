<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

/**
 * Table widget to display disappeared servers
 */
class DisappearedTable extends DataTable
{
    protected $defaultAttributes = [
        'class' => 'usage-table common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    public function createColumns()
    {
        return [
            'hostname' => $this->translate('Hostname'),

            'ip' => [
                'label' => $this->translate('IP'),
                'renderer' => function ($ip) {
                    $ipv4 = ltrim($ip);
                    if (strlen($ipv4) === 4) {
                        $ip = $ipv4;
                    }

                    return inet_ntop($ip);
                }
            ],

            'port' => $this->translate('Port')
        ];
    }
}
