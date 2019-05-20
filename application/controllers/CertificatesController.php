<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\X509\CertificatesTable;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\FilterAdapter;
use Icinga\Module\X509\SortAdapter;
use Icinga\Module\X509\SqlFilter;
use Icinga\Web\Url;
use lipl\Pagination\Adapter\SqlAdapter;
use lipl\Pagination\Paginator;
use ipl\Sql;

class CertificatesController extends Controller
{
    public function indexAction()
    {
        $this
            ->initTabs()
            ->setTitle($this->translate('X.509 Certificates'));

        try {
            $conn = $this->getDb();
        } catch (ConfigurationError $_) {
            $this->render('missing-resource', null, true);
            return;
        }

        $select = (new Sql\Select())
            ->from('x509_certificate c')
            ->columns([
                'c.id', 'c.subject', 'c.issuer', 'c.version', 'c.self_signed', 'c.ca', 'c.trusted',
                'c.pubkey_algo',  'c.pubkey_bits', 'c.signature_algo', 'c.signature_hash_algo',
                'c.valid_from', 'c.valid_to',
            ]);

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

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
            'valid_to' => $this->translate('Valid To'),
            'duration' => $this->translate('Duration'),
            'expires' => $this->translate('Expires')
        ];

        $this->setupSortControl(
            $sortAndFilterColumns,
            new SortAdapter($select, function ($field) {
                if ($field === 'duration') {
                    return '(valid_to - valid_from)';
                } elseif ($field === 'expires') {
                    return 'CASE WHEN UNIX_TIMESTAMP() > valid_to'
                        . ' THEN 0 ELSE (valid_to - UNIX_TIMESTAMP()) / 86400 END';
                }
            })
        );

        $this->setupLimitControl();

        $filterAdapter = new FilterAdapter();
        $this->setupFilterControl(
            $filterAdapter,
            $sortAndFilterColumns,
            ['subject', 'issuer'],
            ['format']
        );
        SqlFilter::apply($select, $filterAdapter->getFilter(), function (FilterExpression $filter) {
            $column = $filter->getColumn();

            if ($column === 'issuer_hash') {
                $value = $filter->getExpression();

                if (is_array($value)) {
                    $value = array_map('hex2bin', $value);
                } else {
                    $value = hex2bin($value);
                }

                $filter->setExpression($value);
            }

            if ($column === 'duration') {
                $expr = clone $filter;
                $expr->setColumn('(valid_to - valid_from)');

                return $expr;
            }

            if ($column === 'expires') {
                $expr = clone $filter;
                $expr->setColumn(
                    'CASE WHEN UNIX_TIMESTAMP() > valid_to THEN 0 ELSE (valid_to - UNIX_TIMESTAMP()) / 86400 END'
                );

                return $expr;
            }

            return false;
        });

        $this->handleFormatRequest($conn, $select, function (\PDOStatement $stmt) {
            foreach ($stmt as $cert) {
                $cert['valid_from'] = (new \DateTime())
                    ->setTimestamp($cert['valid_from'])
                    ->format('l F jS, Y H:i:s e');
                $cert['valid_to'] = (new \DateTime())
                    ->setTimestamp($cert['valid_to'])
                    ->format('l F jS, Y H:i:s e');

                yield $cert;
            }
        });

        $this->view->certificatesTable = (new CertificatesTable())->setData($conn->select($select));
    }
}
