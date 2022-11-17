<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Donut;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Sql\Expression;
use ipl\Stdlib\Filter;

class DashboardController extends Controller
{
    public function indexAction()
    {
        $this->addTitleTab($this->translate('Certificate Dashboard'));

        try {
            $db = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);
            return;
        }

        $byCa = X509Certificate::on($db);
        $byCa
            ->columns([
                'issuer_certificate.subject',
                'cnt' => new Expression('COUNT(*)')
            ])
            ->orderBy('cnt', SORT_DESC)
            ->orderBy('issuer_certificate.subject')
            ->filter(Filter::equal('issuer_certificate.ca', true))
            ->limit(5)
            ->getSelectBase()
            ->groupBy('certificate_issuer_certificate.id');

        $this->view->byCa = (new Donut())
            ->setHeading($this->translate('Certificates by CA'), 2)
            ->setData($byCa)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath('x509/certificates', [
                            'issuer' => $data->issuer_certificate->subject
                        ])->getAbsoluteUrl()
                    ],
                    $data->issuer_certificate->subject
                );
            });

        $duration = X509Certificate::on($db);
        $duration
            ->columns([
                'duration',
                'cnt' => new Expression('COUNT(*)')
            ])
            ->filter(Filter::equal('ca', false))
            ->orderBy('cnt', SORT_DESC)
            ->limit(5)
            ->getSelectBase()
            ->groupBy('duration');

        $this->view->duration = (new Donut())
            ->setHeading($this->translate('Certificates by Duration'), 2)
            ->setData($duration)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath(
                            "x509/certificates?duration={$data['duration']}&ca=n"
                        )->getAbsoluteUrl()
                    ],
                    CertificateUtils::duration($data['duration'])
                );
            });

        $keyStrength = X509Certificate::on($db);
        $keyStrength
            ->columns([
                'pubkey_algo',
                'pubkey_bits',
                'cnt' => new Expression('COUNT(*)')
            ])
            ->orderBy('cnt', SORT_DESC)
            ->limit(5)
            ->getSelectBase()
            ->groupBy(['pubkey_algo', 'pubkey_bits']);

        $this->view->keyStrength = (new Donut())
            ->setHeading($this->translate('Key Strength'), 2)
            ->setData($keyStrength)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath(
                            'x509/certificates',
                            [
                                'pubkey_algo' => $data['pubkey_algo'],
                                'pubkey_bits' => $data['pubkey_bits']
                            ]
                        )->getAbsoluteUrl()
                    ],
                    "{$data['pubkey_algo']} {$data['pubkey_bits']} bits"
                );
            });

        $sigAlgos = X509Certificate::on($db);
        $sigAlgos
            ->columns([
                'signature_algo',
                'signature_hash_algo',
                'cnt' => new Expression('COUNT(*)')
            ])
            ->orderBy('cnt', SORT_DESC)
            ->limit(5)
            ->getSelectBase()
            ->groupBy(['signature_algo', 'signature_hash_algo']);

        $this->view->sigAlgos = (new Donut())
            ->setHeading($this->translate('Signature Algorithms'), 2)
            ->setData($sigAlgos)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath(
                            'x509/certificates',
                            [
                                'signature_hash_algo' => $data['signature_hash_algo'],
                                'signature_algo'      => $data['signature_algo']
                            ]
                        )->getAbsoluteUrl()
                    ],
                    "{$data['signature_hash_algo']} with {$data['signature_algo']}"
                );
            });
    }
}
