<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2+

namespace Icinga\Module\X509;

use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Translation\Translation;

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
                    return Html::tag('i', ['class' => 'icon x509-icon-cert']);
                }
            ],

            'version' => [
                'attributes' => ['class' => 'version-col'],
                'renderer' => function ($version) {
                    return Html::tag('div', ['class' => 'badge'], $version);
                }
            ],

            'subject' => [
                'label' => $this->translate('Subject')
            ],

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

        $url = Url::fromPath('x509/certificate', ['cert' => $row['certificate_id']]);

        $tr->getAttributes()->add(['href' => $url->getAbsoluteUrl()]);

        return $tr;
    }
}
