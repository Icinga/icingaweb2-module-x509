<?php

namespace Icinga\Module\X509\Controllers;

use Icinga\Module\X509\CertificatesTable;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\FilterAdapter;
use Icinga\Module\X509\Paginator;
use Icinga\Module\X509\SortAdapter;
use Icinga\Module\X509\SqlFilter;
use Icinga\Web\Url;
use ipl\Pagination\SqlAdapter;
use ipl\Sql;

class CertificatesController extends Controller
{
    public function indexAction()
    {
        $this
            ->initTabs()
            ->setTitle($this->translate('Certificates'));

        $conn = $this->getDb();

        $select = (new Sql\Select())
            ->from('x509_certificate')
            ->columns([
                'id', 'subject', 'issuer', 'version', 'self_signed', 'ca', 'trusted', 'pubkey_algo',  'pubkey_bits',
                'signature_algo', 'signature_hash_algo', 'valid_from', 'valid_to'
            ]);

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

        $sortAndFilterColumns = [
            'subject' => $this->translate('Subject'),
            'issuer' => $this->translate('Issuer'),
            'version' => $this->translate('Version'),
            'self_signed' => $this->translate('Is Self-signed'),
            'ca' => $this->translate('Is Certificate Authority'),
            'trusted' => $this->translate('Is Trusted'),
            'pubkey_algo' => $this->translate('Public Key Algorithm'),
            'pubkey_bits' => $this->translate('Public Key Strength'),
            'signature_algo' => $this->translate('Signature Algorithm'),
            'signature_hash_algo' => $this->translate('Signature Hash Algorithm'),
            'valid_from' => $this->translate('Valid From'),
            'valid_to' => $this->translate('Valid To')
        ];

        $this->setupSortControl(
            $sortAndFilterColumns,
            new SortAdapter($select)
        );

        $this->setupLimitControl();

        $filterAdapter = new FilterAdapter();
        $this->setupFilterControl(
            $filterAdapter,
            $sortAndFilterColumns,
            ['subject', 'issuer'],
            ['format']
        );
        SqlFilter::apply($filterAdapter->getFilter(), $select);

        $this->handleFormatRequest($conn, $select);

        $this->view->certificatesTable = (new CertificatesTable())->setData($conn->select($select));
    }
}
