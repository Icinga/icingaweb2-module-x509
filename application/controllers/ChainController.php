<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\ChainDetails;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\DbTool;
use ipl\Html\Attribute;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Sql;

class ChainController extends Controller
{
    public function indexAction()
    {
        $id = $this->params->getRequired('id');

        try {
            $conn = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);
            return;
        }

        $chainSelect = (new Sql\Select())
            ->from('x509_certificate_chain ch')
            ->columns('*')
            ->join('x509_target t', 't.id = ch.target_id')
            ->where(['ch.id = ?' => $id]);

        $chain = $conn->select($chainSelect)->fetch();

        if ($chain === false) {
            $this->httpNotFound($this->translate('Certificate not found.'));
        }

        $this->setTitle($this->translate('X.509 Certificate Chain'));

        $ip = DbTool::unmarshalBinary($chain['ip']);
        $ipv4 = ltrim($ip, "\0");
        if (strlen($ipv4) === 4) {
            $ip = $ipv4;
        }

        $chainInfo = Html::tag('div');
        $chainInfo->add(Html::tag('dl', [
            Html::tag('dt', $this->translate('Host')),
            Html::tag('dd', $chain['hostname']),
            Html::tag('dt', $this->translate('IP')),
            Html::tag('dd', inet_ntop($ip)),
            Html::tag('dt', $this->translate('Port')),
            Html::tag('dd', $chain['port'])
        ]));

        $valid = Html::tag('div', ['class' => 'cert-chain']);

        if ($chain['valid'] === 'yes') {
            $valid->getAttributes()->add('class', '-valid');
            $valid->add(Html::tag('p', $this->translate('Certificate chain is valid.')));
        } else {
            $valid->getAttributes()->add('class', '-invalid');
            $valid->add(Html::tag('p', sprintf(
                $this->translate('Certificate chain is invalid: %s.'),
                $chain['invalid_reason']
            )));
        }

        $certsSelect = (new Sql\Select())
            ->from('x509_certificate c')
            ->columns('*')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_id = c.id')
            ->join('x509_certificate_chain cc', 'cc.id = ccl.certificate_chain_id')
            ->where(['cc.id = ?' => $id])
            ->orderBy('ccl.order');

        $this->view->chain = (new HtmlDocument())
            ->add($chainInfo)
            ->add($valid)
            ->add((new ChainDetails())->setData($conn->select($certsSelect)));
    }
}
