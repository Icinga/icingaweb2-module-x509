<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\CertificateUtils;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Donut;
use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Sql\Select;

class DashboardController extends Controller
{
    public function indexAction()
    {
        $this->setTitle($this->translate('Certificate Dashboard'));

        try {
            $db = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);
            return;
        }

        $byCa = $db->select(
            (new Select())
                ->from('x509_certificate i')
                ->columns(['i.subject', 'cnt' => 'COUNT(*)'])
                ->join('x509_certificate c', ['c.issuer_hash = i.subject_hash', 'i.ca = ?' => 'yes'])
                ->groupBy(['i.id'])
                ->orderBy('cnt', SORT_DESC)
                ->limit(5)
        );

        $this->view->byCa = (new Donut())
            ->setHeading($this->translate('Certificates by CA'), 2)
            ->setData($byCa)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath('x509/certificates', ['issuer' => $data['subject']])->getAbsoluteUrl()
                    ],
                    $data['subject']
                );
            });

        $duration = $db->select(
            (new Select())
                ->from('x509_certificate')
                ->columns([
                    'duration' => 'valid_to - valid_from',
                    'cnt' => 'COUNT(*)'
                ])
                ->where(['ca = ?' => 'no'])
                ->groupBy(['duration'])
                ->orderBy('cnt', SORT_DESC)
                ->limit(5)
        );

        $this->view->duration = (new Donut())
            ->setHeading($this->translate('Certificates by Duration'), 2)
            ->setData($duration)
            ->setLabelCallback(function ($data) {
                return Html::tag(
                    'a',
                    [
                        'href' => Url::fromPath(
                            "x509/certificates?duration={$data['duration']}&ca=no"
                        )->getAbsoluteUrl()
                    ],
                    CertificateUtils::duration($data['duration'])
                );
            });

        $keyStrength = $db->select(
            (new Select())
                ->from('x509_certificate')
                ->columns(['pubkey_algo', 'pubkey_bits', 'cnt' => 'COUNT(*)'])
                ->groupBy(['pubkey_algo', 'pubkey_bits'])
                ->orderBy('cnt', SORT_DESC)
                ->limit(5)
        );

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

        $sigAlgos = $db->select(
            (new Select())
                ->from('x509_certificate')
                ->columns(['signature_algo', 'signature_hash_algo', 'cnt' => 'COUNT(*)'])
                ->groupBy(['signature_algo', 'signature_hash_algo'])
                ->orderBy('cnt', SORT_DESC)
                ->limit(5)
        );

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
                                'signature_algo' => $data['signature_algo']
                            ]
                        )->getAbsoluteUrl()
                    ],
                    "{$data['signature_hash_algo']} with {$data['signature_algo']}"
                );
            });
    }
}
