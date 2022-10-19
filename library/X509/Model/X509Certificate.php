<?php

namespace Icinga\Module\X509\Model;

use Icinga\Module\X509\Model\Behavior\BoolCast;
use Icinga\Module\X509\Model\Behavior\DistinguishedEncodingRules;
use Icinga\Module\X509\Model\Behavior\ExpressionInjector;
use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behaviors;
use ipl\Orm\Model;
use ipl\Orm\Relations;
use ipl\Sql\Expression;

class X509Certificate extends Model
{
    public function getTableName()
    {
        return 'x509_certificate';
    }

    public function getTableAlias(): string
    {
        return 'certificate';
    }

    public function getKeyName()
    {
        return 'id';
    }

    public function getColumns()
    {
        return [
            'id',
            'subject',
            'subject_hash',
            'issuer',
            'issuer_hash',
            'issuer_certificate_id',
            'version',
            'self_signed',
            'ca',
            'trusted',
            'pubkey_algo',
            'pubkey_bits',
            'signature_algo',
            'signature_hash_algo',
            'valid_from',
            'valid_to',
            'fingerprint',
            'serial',
            'certificate',
            'ctime',
            'mtime',
            'duration' => new Expression('%s - %s', ['valid_to', 'valid_from']),
            'expires'  => new Expression(
                'CASE WHEN UNIX_TIMESTAMP() > %s THEN 0 ELSE (%s - UNIX_TIMESTAMP()) / 86400 END',
                ['valid_to', 'valid_to']
            )
        ];
    }

    public function getColumnDefinitions()
    {
        return [
            'subject'             => t('Certificate'),
            'issuer'              => t('Issuer'),
            'version'             => t('Version'),
            'self_signed'         => t('Is Self-Signed'),
            'ca'                  => t('Is Certificate Authority'),
            'trusted'             => t('Is Trusted'),
            'pubkey_algo'         => t('Public Key Algorithm'),
            'pubkey_bits'         => t('Public Key Strength'),
            'signature_algo'      => t('Signature Algorithm'),
            'signature_hash_algo' => t('Signature Hash Algorithm'),
            'valid_from'          => t('Valid From'),
            'valid_to'            => t('Valid To'),
            'duration'            => t('Duration'),
            'expires'             => t('Expiration'),
            'subject_hash'        => t('Subject Hash'),
            'issuer_hash'         => t('Issuer Hash'),
        ];
    }

    public function getSearchColumns()
    {
        return ['subject', 'issuer'];
    }

    public function createBehaviors(Behaviors $behaviors)
    {
        $behaviors->add(new Binary([
            'subject_hash',
            'issuer_hash',
            'fingerprint',
            'serial',
            'certificate'
        ]));

        $behaviors->add(new DistinguishedEncodingRules(['certificate']));

        $behaviors->add(new BoolCast([
            'ca',
            'trusted',
            'self_signed'
        ]));

        $behaviors->add(new ExpressionInjector('duration', 'expires'));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('issuer_certificate', self::class)
            ->setForeignKey('subject_hash')
            ->setCandidateKey('issuer_hash');
        $relations->belongsToMany('chain', X509CertificateChain::class)
            ->through(X509CertificateChainLink::class)
            ->setForeignKey('certificate_id');

        $relations->hasMany('alt_name', X509CertificateSubjectAltName::class)
            ->setJoinType('LEFT');
        $relations->hasMany('dn', X509Dn::class)
            ->setForeignKey('hash')
            ->setCandidateKey('subject_hash')
            ->setJoinType('LEFT');
    }
}
