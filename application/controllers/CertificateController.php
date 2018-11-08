<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\CertificateDetails;
use Icinga\Module\X509\Controller;
use ipl\Sql;

class CertificateController extends Controller
{
    public function indexAction()
    {
        $certId = $this->params->getRequired('cert');

        $conn = $this->getDb();

        $cert = $conn->select(
            (new Sql\Select())
                ->from('x509_certificate')
                ->columns('*')
                ->where(['id = ?' => $certId])
        )->fetch();

        if ($cert === false) {
            $this->httpNotFound($this->translate('Certificate not found.'));
        }

        $this->setTitle($this->translate('X.509 Certificate'));

        $this->view->certificateDetails = (new CertificateDetails())
            ->setCert($cert);
    }
}
