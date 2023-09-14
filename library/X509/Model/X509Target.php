<?php

/* Icinga Web 2 X.509 Module | (c) 2022 Icinga GmbH | GPLv2 */

namespace Icinga\Module\X509\Model;

use Icinga\Module\X509\Model\Behavior\Ip;
use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

class X509Target extends Model
{
    public function getTableName()
    {
        return 'x509_target';
    }

    public function getTableAlias(): string
    {
        return 'target';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'ip',
            'port',
            'hostname',
            'latest_certificate_chain_id',
            'last_scan',
            'ctime',
            'mtime'
        ];
    }

    public function getColumnDefinitions()
    {
        return [
            'hostname' => t('Host Name'),
            'ip'       => t('IP'),
            'port'     => t('Port')
        ];
    }

    public function getSearchColumns()
    {
        return ['hostname'];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Ip(['ip']));

        $behaviors->add(new MillisecondTimestamp([
            'ctime',
            'mtime',
            'last_scan'
        ]));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('chain', X509CertificateChain::class)
            ->setCandidateKey('latest_certificate_chain_id');
    }
}
