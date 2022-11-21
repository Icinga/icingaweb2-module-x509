<?php

// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\CertificateDetails;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Model\X509Certificate;
use ipl\Sql;
use ipl\Stdlib\Filter;

class CertificateController extends Controller
{
    public function indexAction()
    {
        $certId = $this->params->getRequired('cert');

        try {
            $conn = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);

            return;
        }

        $cert = X509Certificate::on($conn)
            ->filter(Filter::equal('id', $certId))
            ->first();

        if (! $cert) {
            $this->httpNotFound($this->translate('Certificate not found.'));
        }

        $this->addTitleTab($this->translate('X.509 Certificate'));
        $this->getTabs()->disableLegacyExtensions();

        $this->view->certificateDetails = (new CertificateDetails())
            ->setCert($cert);
    }
}
