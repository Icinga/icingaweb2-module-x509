<?php

namespace Icinga\Module\X509;

use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Translation\Translation;

/**
 * Table widget to display X.509 certificate usage
 */
class UsageTable extends DataTable
{
    use Translation;

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
                    $icon = $valid === 'yes' ? 'check' : 'block';

                    return Html::tag('i', ['class' => "icon icon-{$icon}"]);
                }
            ],

            'host' => [
                'column' => 'ip',
                'label' => $this->translate('Host'),
                'renderer' => function ($ip, $data) {
                    if (! empty($data['sni_name'])) {
                        return $data['sni_name'];
                    }

                    return gethostbyaddr(inet_ntop($ip));
                }
            ],

            'ip' => [
                'label' => $this->translate('IP'),
                'renderer' => function ($ip) {
                    return inet_ntop($ip);
                }
            ],

            'port' => $this->translate('Port'),

            'subject' => $this->translate('Certificate'),

            'signature_algo' => [
                'label' => $this->translate('Signature Algorithm'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data['signature_hash_algo']}";
                }
            ],

            'pubkey_algo' => [
                'label' => $this->translate('Public Key'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data['pubkey_bits']}";
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
