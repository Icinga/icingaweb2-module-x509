<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509;

use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Web\Url;
use ipl\Html\Html;

/**
 * Table widget to display X.509 chain details
 */
class ChainDetails extends DataTable
{
    protected $defaultAttributes = [
        'class' => 'cert-table common-table table-row-selectable',
        'data-base-target' => '_next'
    ];

    public function createColumns()
    {
        return [
            [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function () {
                    return Html::tag('i', ['class' => 'x509-icon-cert']);
                }
            ],

            'version' => [
                'attributes' => ['class' => 'version-col'],
                'renderer' => function ($version) {
                    return Html::tag('div', ['class' => 'badge'], $version);
                }
            ],

            'subject' => [
                'label' => mt('x509', 'Subject', 'x509.certificate')
            ],

            'ca' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($ca) {
                    if (! $ca) {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'x509-icon-ca', 'title' => mt('x509', 'Is Certificate Authority')]
                    );
                }
            ],

            'self_signed' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($selfSigned) {
                    if (! $selfSigned) {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'x509-icon-self-signed', 'title' => mt('x509', 'Is Self-Signed')]
                    );
                }
            ],

            'trusted' => [
                'attributes' => ['class' => 'icon-col'],
                'renderer' => function ($trusted) {
                    if (! $trusted) {
                        return null;
                    }

                    return Html::tag(
                        'i',
                        ['class' => 'icon icon-thumbs-up', 'title' => mt('x509', 'Is Trusted')]
                    );
                }
            ],

            'signature_algo' => [
                'label' => mt('x509', 'Signature Algorithm'),
                'renderer' => function ($algo, $data) {
                    return "{$data->signature_hash_algo} with $algo";
                }
            ],

            'pubkey_algo' => [
                'label' => mt('x509', 'Public Key'),
                'renderer' => function ($algo, $data) {
                    return "$algo {$data->pubkey_bits} bits";
                }
            ],

            'valid_to' => [
                'attributes' => ['class' => 'expiration-col'],
                'label' => mt('x509', 'Expiration'),
                'renderer' => function ($to, $data) {
                    return new ExpirationWidget($data->valid_from, $to);
                }
            ]
        ];
    }

    protected function renderRow(X509Certificate $row)
    {
        $tr = parent::renderRow($row);

        $url = Url::fromPath('x509/certificate', ['cert' => $row->id]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
