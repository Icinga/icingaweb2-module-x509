<?php

namespace Icinga\Module\X509\Model;

use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;

class X509CertificateSubjectAltName extends Model
{
    public function getTableName()
    {
        return 'x509_certificate_subject_alt_name';
    }

    public function getTableAlias(): string
    {
        return 'alt_name';
    }

    public function getKeyName()
    {
        return ['certificate_id', 'hash'];
    }

    public function getColumns()
    {
        return [
            'certificate_id',
            'hash',
            'type',
            'value',
            'ctime'
        ];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Binary(['hash']));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('certificate', X509Certificate::class);
    }
}
