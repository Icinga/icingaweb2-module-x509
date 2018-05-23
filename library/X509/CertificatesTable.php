<?php

namespace Icinga\Module\X509;

use Icinga\Web\Url;
use ipl\Html\Html;

/**
 * Table widget to display X.509 certificates
 */
class CertificatesTable extends DataTable
{
    protected $defaultAttributes = [
        'class' => 'cert-table common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    protected function createColumns()
    {
        return [
            'version' => [
                'attributes' => ['class' => 'version-col'],
                'renderer' => function ($version) {
                    return Html::tag('div', ['class' => 'badge'], $version);
                }
            ],

            'ca' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($ca) {
                    if ($ca === 'no') {
                        return null;
                    }

                    return Html::tag('i', ['class' => 'icon x509-icon-ca']);
                }
            ],

            'self_signed' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($selfSigned) {
                    if ($selfSigned === 'no') {
                        return null;
                    }

                    return Html::tag('i', ['class' => 'icon x509-icon-self-signed']);
                }
            ],

            'subject' => $this->translate('Certificate'),

            'issuer' => $this->translate('Issuer'),

            'signature_algo' => [
                'label' => $this->translate('Signature Algorithm'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data['signature_hash_algo']}";
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

        $url = Url::fromPath('x509/certificate', ['cert' => $row['id']]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
