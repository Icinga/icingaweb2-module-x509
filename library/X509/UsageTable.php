<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Web\Url;
use ipl\Html\Html;

/**
 * Table widget to display X.509 certificate usage
 */
class UsageTable extends DataTable
{
    protected $defaultAttributes = [
        'class' => 'usage-table common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    public function createColumns()
    {
        return [
            'valid' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($valid) {
                    $icon = $valid === 'yes' ? 'check -ok' : 'block -critical';

                    return Html::tag('i', ['class' => "icon icon-{$icon}"]);
                }
            ],

            'hostname' => mt('x509', 'Hostname'),

            'ip' => [
                'label' => mt('x509', 'IP'),
                'renderer' => function ($ip) {
                    $ipv4 = ltrim($ip, "\0");
                    if (strlen($ipv4) === 4) {
                        $ip = $ipv4;
                    }

                    return inet_ntop($ip);
                }
            ],

            'port' => mt('x509', 'Port'),

            'subject' => mt('x509', 'Certificate'),

            'signature_algo' => [
                'label' => mt('x509', 'Signature Algorithm'),
                'renderer' => function ($algo, $data) {
                    return "{$data['signature_hash_algo']} with $algo";
                }
            ],

            'pubkey_algo' => [
                'label' => mt('x509', 'Public Key'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data['pubkey_bits']} bits";
                }
            ],

            'valid_to' => [
                'attributes' => ['class' => 'expiration-col'],
                'label' => mt('x509', 'Expires'),
                'renderer' => function ($to, $data) {
                    return new ExpirationWidget($data['valid_from'], $to);
                }
            ]
        ];
    }

    protected function renderRow($row)
    {
        $tr = parent::renderRow($row);

        $url = Url::fromPath('x509/chain', ['id' => $row['certificate_chain_id']]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
