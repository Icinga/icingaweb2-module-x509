<?php

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\Controller;
use Icinga\Module\X509\FilterAdapter;
use Icinga\Module\X509\Paginator;
use Icinga\Module\X509\SortAdapter;
use Icinga\Module\X509\SqlFilter;
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
            ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->where(['ccl.order = ?' => 0]);

        $sortAndFilterColumns = [
            'subject' => $this->translate('Certificate'),
            'issuer' => $this->translate('Issuer'),
            'version' => $this->translate('Version'),
            'self_signed' => $this->translate('Is Self-Signed'),
            'ca' => $this->translate('Is Certificate Authority'),
            'trusted' => $this->translate('Is Trusted'),
            'pubkey_algo' => $this->translate('Public Key Algorithm'),
            'pubkey_bits' => $this->translate('Public Key Strength'),
            'signature_algo' => $this->translate('Signature Algorithm'),
            'signature_hash_algo' => $this->translate('Signature Hash Algorithm'),
            'valid_from' => $this->translate('Valid From'),
            'valid_to' => $this->translate('Valid To')
        ];

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

        $this->setupSortControl(
            $sortAndFilterColumns,
            new SortAdapter($select)
        );

        $this->setupLimitControl();

        $filterAdapter = new FilterAdapter();
        $this->setupFilterControl(
            $filterAdapter,
            $sortAndFilterColumns,
            ['subject'],
            ['format']
        );
        SqlFilter::apply($filterAdapter->getFilter(), $select);

        $this->handleFormatRequest($conn, $select);

        $this->view->usageTable = (new UsageTable())->setData($conn->select($select));
    }
}
