<?php
// Icinga Web 2 X.509 Module | (c) 2018 Icinga GmbH | GPLv2+

namespace Icinga\Module\X509\Controllers;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\FilterAdapter;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\SortAdapter;
use Icinga\Module\X509\SqlFilter;
use Icinga\Module\X509\UsageTable;
use Icinga\Web\Url;
use ipl\Pagination\Adapter\SqlAdapter;
use ipl\Pagination\Paginator;
use ipl\Sql;

class UsageController extends Controller
{
    public function indexAction()
    {
        $this
            ->initTabs()
            ->setTitle($this->translate('X.509 Certificate Usage'));

        $conn = $this->getDb();

        $select = (new Sql\Select())
            ->from('x509_target t')
            ->columns('*')
            ->join('x509_certificate_chain cc', 'cc.id = t.latest_certificate_chain_id')
            ->join('x509_certificate_chain_link ccl', 'ccl.certificate_chain_id = cc.id')
            ->join('x509_certificate c', 'c.id = ccl.certificate_id')
            ->where(['ccl.order = ?' => 0]);

        $sortAndFilterColumns = [
            'hostname' => $this->translate('Hostname'),
            'ip' => $this->translate('IP'),
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
            'valid' => $this->translate('Chain Is Valid')
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
            ['hostname', 'subject'],
            ['format']
        );
        SqlFilter::apply($select, $filterAdapter->getFilter(), function (FilterExpression $filter) {
            $column = $filter->getColumn();

            if ($column === 'ip') {
                $value = $filter->getExpression();

                if (is_array($value)) {
                    $value = array_map('Job::binary', $value);
                } else {
                    $value = Job::binary($value);
                }

                $filter->setExpression($value);
            }

            if ($column === 'issuer_hash') {
                $value = $filter->getExpression();

                if (is_array($value)) {
                    $value = array_map('hex2bin', $value);
                } else {
                    $value = hex2bin($value);
                }

                $filter->setExpression($value);
            }

            return false;
        });

        $formatQuery = clone $select;
        $formatQuery->resetColumns()->columns([
            'valid', 'hostname', 'ip', 'port', 'subject', 'issuer', 'version', 'self_signed', 'ca', 'trusted', 'pubkey_algo',  'pubkey_bits',
            'signature_algo', 'signature_hash_algo', 'valid_from', 'valid_to'
        ]);

        $this->handleFormatRequest($conn, $formatQuery, function (\PDOStatement $stmt) {
            foreach ($stmt as $usage) {
                $usage['valid_from'] = (new \DateTime())
                    ->setTimestamp($usage['valid_from'])
                    ->format('l F jS, Y H:i:s e');
                $usage['valid_to'] = (new \DateTime())
                    ->setTimestamp($usage['valid_to'])
                    ->format('l F jS, Y H:i:s e');

                $ip = $usage['ip'];
                $ipv4 = ltrim($ip);
                if (strlen($ipv4) === 4) {
                    $ip = $ipv4;
                }
                $usage['ip'] = inet_ntop($ip);

                yield $usage;
            }
        });

        $this->view->usageTable = (new UsageTable())->setData($conn->select($select));
    }
}
