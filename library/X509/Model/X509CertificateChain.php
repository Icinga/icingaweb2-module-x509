<?php

namespace Icinga\Module\X509\Model;

use ipl\Orm\Behavior\BoolCast;
use ipl\Orm\Behavior\MillisecondTimestamp;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

class X509CertificateChain extends Model
{
    public function getTableName()
    {
        return 'x509_certificate_chain';
    }

    public function getTableAlias(): string
    {
        return 'chain';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'target_id',
            'length',
            'valid',
            'invalid_reason',
            'ctime'
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new BoolCast(['valid']));

        $behaviors->add(new MillisecondTimestamp(['ctime']));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('target', X509Target::class)
            ->setCandidateKey('id')
            ->setForeignKey('latest_certificate_chain_id');

        $relations->belongsToMany('certificate', X509Certificate::class)
            ->through(X509CertificateChainLink::class)
            ->setForeignKey('certificate_chain_id');
    }
}
