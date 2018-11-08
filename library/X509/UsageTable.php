<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Translation\Translation;

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

            'port' => $this->translate('Port'),

            'subject' => $this->translate('Certificate'),

            'signature_algo' => [
                'label' => $this->translate('Signature Algorithm'),
                'renderer' => function ($algo, $data) {
                    return "{$data['signature_hash_algo']} with $algo";
                }
            ],

            'pubkey_algo' => [
                'label' => $this->translate('Public Key'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data['pubkey_bits']} bits";
                }
            ],

            'valid_to' => [
                'attributes' => ['class' => 'expiration-col'],
                'label' => $this->translate('Expires'),
                'renderer' => function ($to, $data) {
                    return new ExpirationWidget($data['valid_from'], $to);
                }
            ]
        ];
    }

    protected function renderRow($row)
    {
        $tr = parent::renderRow($row);

        $url = Url::fromPath('x509/chain', ['cert' => $row['certificate_id'], 'target' => $row['target_id']]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
