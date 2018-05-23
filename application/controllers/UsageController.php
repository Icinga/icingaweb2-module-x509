<?php

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Controller;
use Icinga\Module\X509\Paginator;
use Icinga\Module\X509\UsageTable;
use Icinga\Web\Url;
use ipl\Pagination\SqlAdapter;
use ipl\Sql;

class UsageController extends Controller
{
    public function indexAction()
    {
        $this->setTitle($this->translate('X.509 Certificate Usage'));

        $conn = $this->getDb();

        $select = (new Sql\Select())
            ->from('x509_target t')
            ->columns('*')
            ->join('x509_certificate_chain cc', 'cc.target_id = t.id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->where(['ccl.order = ?' => 0]);

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

        $usage = $conn->select($select);

        $this->view->usageTable = (new UsageTable())->setData($usage);
    }
}
