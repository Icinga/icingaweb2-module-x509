<?php

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\CertificatesTable;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Paginator;
use Icinga\Web\Url;
use ipl\Pagination\SqlAdapter;
use ipl\Sql;

class CertificatesController extends Controller
{
    public function indexAction()
    {
        $this->setTitle($this->translate('Certificates'));

        $conn = $this->getDb();

        $select = (new Sql\Select())
            ->from('x509_certificate')
            ->columns('*');

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

        $certificates = $conn->select($select);

        $this->view->certificatesTable = (new CertificatesTable())->setData($certificates);
    }
}
