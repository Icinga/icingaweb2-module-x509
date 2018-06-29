<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH

namespace Icinga\Module\X509\Controllers;

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
        $this->setTitle($this->translate('X.509 Dashboard'));

        $db = $this->getDb();

        $byCa = $db->select((new Select())
            ->from('x509_certificate c')
            ->columns(['c.issuer', 'c.subject_hash', 'c.issuer_hash', 'cnt' => 'COUNT(*)'])
            ->join('x509_certificate i', ['i.subject_hash = c.issuer_hash', 'i.ca' => 'yes'])
            ->groupBy(['c.issuer_hash'])
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
                        'href' => Url::fromPath('x509/certificates', ['issuer' => $data['issuer']])->getAbsoluteUrl()
                    ],
                    $data['issuer']
                );
            });

        $duration = $db->select((new Select())
            ->from('x509_certificate')
            ->columns([
                'duration' => 'valid_to - valid_from',
                'valid_from' => 'valid_from',
                'valid_to' => 'valid_to',
                'cnt' => 'COUNT(*)'
            ])
            ->where(['ca = ?' => 'no'])
            ->groupBy('duration')
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
                            "x509/certificates?valid_from>={$data['valid_from']}&valid_to<={$data['valid_to']}&ca=no"
                        )->getAbsoluteUrl()
                    ],
                    CertificateUtils::duration($data['duration'])
                );
            });

        $keyStrength = $db->select((new Select())
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

        $sigAlgos = $db->select((new Select())
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
