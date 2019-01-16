<?php
// Icinga Web 2 X.509 Module | (c) 2019 Icinga GmbH | GPLv2

namespace Icinga\Module\X509\Controllers;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\X509\Controller;
use Icinga\Module\X509\DisappearedTable;
use Icinga\Module\X509\FilterAdapter;
use Icinga\Module\X509\Job;
use Icinga\Module\X509\SortAdapter;
use Icinga\Module\X509\SqlFilter;
use Icinga\Web\Url;
use ipl\Pagination\Adapter\SqlAdapter;
use ipl\Pagination\Paginator;
use ipl\Sql;
use PDOStatement;

class DisappearedController extends Controller
{
    public function indexAction()
    {
        $this->initTabs()
            ->setTitle($this->translate('X.509 Disappeared Servers'));

        $conn = $this->getDb();

        $select = (new Sql\Select())
            ->from('x509_target')
            ->columns('*')
            ->where(['latest_certificate_chain_id IS NULL']);

        $sortAndFilterColumns = [
            'hostname' => $this->translate('Hostname'),
            'ip' => $this->translate('IP')
        ];

        $this->view->paginator = new Paginator(new SqlAdapter($conn, $select), Url::fromRequest());

        $this->setupSortControl($sortAndFilterColumns, new SortAdapter($select));

        $this->setupLimitControl();

        $filterAdapter = new FilterAdapter();
        $this->setupFilterControl($filterAdapter, $sortAndFilterColumns, ['hostname'], ['format']);
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

            return false;
        });

        $formatQuery = clone $select;
        $formatQuery->resetColumns()->columns(['hostname', 'ip', 'port']);

        $this->handleFormatRequest($conn, $formatQuery, function (PDOStatement $stmt) {
            foreach ($stmt as $usage) {
                $ip = $usage['ip'];
                $ipv4 = ltrim($ip);
                if (strlen($ipv4) === 4) {
                    $ip = $ipv4;
                }
                $usage['ip'] = inet_ntop($ip);

                yield $usage;
            }
        });

        $this->view->usageTable = (new DisappearedTable())->setData($conn->select($select));
    }
}
