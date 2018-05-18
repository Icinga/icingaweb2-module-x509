<?php

namespace Icinga\Module\X509;

use DateTime;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Translation\Translation;

/**
 * Widget to display X.509 certificate details
 */
class CertificateDetails extends BaseHtmlElement
{
    use Translation;

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'cert-details'];

    /**
     * @var array
     */
    protected $cert;

    public function setCert(array $cert)
    {
        $this->cert = $cert;

        return $this;
    }

    protected function assemble()
    {
        $pem = CertificateUtils::der2pem($this->cert['certificate']);
        $cert = openssl_x509_parse($pem);
//        $pubkey = openssl_pkey_get_details(openssl_get_publickey($pem));

        $subject = Html::tag('dl');
        foreach ($cert['subject'] as $key => $value) {
            $subject->add([
                Html::content('dt', $key),
                Html::content('dd', $value)
            ]);
        }

        $issuer = Html::tag('dl');
        foreach ($cert['issuer'] as $key => $value) {
            $issuer->add([
                Html::content('dt', $key),
                Html::content('dd', $value)
            ]);
        }

        $certInfo = Html::tag('dl');
        $certInfo->add([
            Html::content('dt', $this->translate('Serial Number')),
            Html::content('dd', bin2hex($this->cert['serial'])),
            Html::content('dt', $this->translate('Version')),
            Html::content('dd', $this->cert['version']),
            Html::content('dt', $this->translate('Signature Algorithm')),
            Html::content('dd', $this->cert['signature_algo'] . ' with ' . $this->cert['signature_hash_algo']),
            Html::content('dt', $this->translate('Not Valid Before')),
            Html::content('dd', (new DateTime())->setTimestamp($this->cert['valid_from'])->format('l F jS, Y H:i:s e')),
            Html::content('dt', $this->translate('Not Valid After')),
            Html::content('dd', (new DateTime())->setTimestamp($this->cert['valid_to'])->format('l F jS, Y H:i:s e')),
        ]);

        $pubkeyInfo = Html::tag('dl');
        $pubkeyInfo->add([
            Html::content('dt', $this->translate('Algorithm')),
            Html::content('dd', $this->cert['pubkey_algo']),
            Html::content('dt', $this->translate('Key Size')),
            Html::content('dd', $this->cert['pubkey_bits'])
        ]);

        $extensions = Html::tag('dl');
        foreach ($cert['extensions'] as $key => $value) {
            $extensions->add([
                Html::content('dt', ucwords(implode(' ', preg_split('/(?=[A-Z])/', $key)))),
                Html::content('dd', $value)
            ]);
        }

        $fingerprints = Html::tag('dl');
        $fingerprints->add([
            Html::content('dt', 'SHA-256'),
            Html::content('dd', wordwrap(strtoupper(bin2hex($this->cert['fingerprint'])), 2, ' ', true))
        ]);

        $this->add([
            Html::content('h2', [Html::tag('i', ['class' => 'icon x509-icon-cert']), $this->cert['subject']]),
            Html::content('h3', $this->translate('Subject Name')),
            $subject,
            Html::content('h3', $this->translate('Issuer Name')),
            $issuer,
            Html::content('h3', $this->translate('Certificate Info')),
            $certInfo,
            Html::content('h3', $this->translate('Public Key Info')),
            $pubkeyInfo,
            Html::content('h3', $this->translate('Extensions')),
            $extensions,
            Html::content('h3', $this->translate('Fingerprints')),
            $fingerprints
        ]);
    }
}
