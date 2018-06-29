<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

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

            'subject' => $this->translate('Certificate'),

            'ca' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($ca) {
                    if ($ca === 'no') {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'icon x509-icon-ca', 'title' => $this->translate('Is Certificate Authority')]
                    );
                }
            ],

            'self_signed' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($selfSigned) {
                    if ($selfSigned === 'no') {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'icon x509-icon-self-signed', 'title' => $this->translate('Is Self-Signed')]
                    );
                }
            ],

            'trusted' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($trusted) {
                    if ($trusted === 'no') {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'icon icon-thumbs-up', 'title' => $this->translate('Is Trusted')]
                    );
                }
            ],

            'issuer' => $this->translate('Issuer'),

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

        $url = Url::fromPath('x509/certificate', ['cert' => $row['id']]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
