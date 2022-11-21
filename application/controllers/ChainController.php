<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\ChainDetails;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Model\X509Certificate;
use Icinga\Module\X509\Model\X509CertificateChain;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Stdlib\Filter;

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

        $chain = X509CertificateChain::on($conn)
            ->with(['target'])
            ->filter(Filter::equal('id', $id))
            ->first();

        if (! $chain) {
            $this->httpNotFound($this->translate('Certificate not found.'));
        }

        $this->addTitleTab($this->translate('X.509 Certificate Chain'));
        $this->getTabs()->disableLegacyExtensions();

        $chainInfo = Html::tag('div');
        $chainInfo->add(Html::tag('dl', [
            Html::tag('dt', $this->translate('Host')),
            Html::tag('dd', $chain->target->hostname),
            Html::tag('dt', $this->translate('IP')),
            Html::tag('dd', $chain->target->ip),
            Html::tag('dt', $this->translate('Port')),
            Html::tag('dd', $chain->target->port)
        ]));

        $valid = Html::tag('div', ['class' => 'cert-chain']);

        if ($chain['valid']) {
            $valid->getAttributes()->add('class', '-valid');
            $valid->add(Html::tag('p', $this->translate('Certificate chain is valid.')));
        } else {
            $valid->getAttributes()->add('class', '-invalid');
            $valid->add(Html::tag('p', sprintf(
                $this->translate('Certificate chain is invalid: %s.'),
                $chain['invalid_reason']
            )));
        }

        $certs = X509Certificate::on($conn)->with(['chain']);
        $certs
            ->filter(Filter::equal('chain.id', $id))
            ->getSelectBase()
            ->orderBy('certificate_link.order');

        $this->view->chain = (new HtmlDocument())
            ->add($chainInfo)
            ->add($valid)
            ->add((new ChainDetails())->setData($certs));
    }
}
