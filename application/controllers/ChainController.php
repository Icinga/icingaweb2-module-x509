<?php
/* X509 module | (c) 2018 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\CertificateDetails;
use Icinga\Module\X509\ChainDetails;
use Icinga\Module\X509\Controller;
use ipl\Html\Attribute;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Sql;

class ChainController extends Controller
{
    public function indexAction()
    {
        $certId = $this->params->getRequired('cert');
        $targetId = $this->params->getRequired('target');

        $conn = $this->getDb();

        $certSelect = (new Sql\Select())
            ->from('x509_target t')
            ->columns('*')
            ->join('x509_certificate_chain ch', 'ch.target_id = t.id')
            ->join('x509_certificate_chain_link chc', 'chc.certificate_chain_id = ch.id')
            ->join('x509_certificate c', 'c.id = chc.certificate_id')
            ->where(['chc.order = ?' => 0, 'chc.certificate_id = ?' => $certId, 't.id = ?' => $targetId]);

        $cert = $conn->select($certSelect)->fetch();

        if ($cert === false) {
            $this->httpNotFound($this->translate('Certificate not found.'));
        }

        $this->setTitle($this->translate('X.509 Certificate Chain'));

        $chainInfo = Html::tag('div');
        $chainInfo->add(Html::tag('dl', [
            Html::tag('dt', $this->translate('Host')),
            Html::tag('dd', $cert['hostname']),
            Html::tag('dt', $this->translate('IP')),
            Html::tag('dd', inet_ntop($cert['ip'])),
            Html::tag('dt', $this->translate('Port')),
            Html::tag('dd', $cert['port'])
        ]));

        $valid = Html::tag('div', ['class' => 'cert-chain']);

        if ($cert['valid'] === 'yes') {
            $valid->getAttributes()->add('class', '-valid');
            $valid->add(Html::tag('p', $this->translate('Certificate chain is valid.')));
        } else {
            $valid->getAttributes()->add('class', '-invalid');
            $valid->add(Html::tag('p', sprintf($this->translate('Certificate chain is invalid: %s.'), $cert['invalid_reason'])));
        }

        $chainSselect = (new Sql\Select())
            ->from('x509_certificate c')
            ->columns('*')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_id = c.id')
            ->join('x509_certificate_chain cc', 'cc.id = ccl.certificate_chain_id')
            ->where(['cc.id = ?' => $cert['certificate_chain_id']])
            ->orderBy('ccl.order');

        $this->view->chain = (new HtmlDocument())
            ->add($chainInfo)
            ->add($valid)
            ->add((new ChainDetails())->setData($conn->select($chainSselect)));
    }
}
