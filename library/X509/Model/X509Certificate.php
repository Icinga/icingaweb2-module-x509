<?php

namespace Icinga\Module\X509\Model;

use Icinga\Module\X509\Model\Behavior\DERBase64;
use Icinga\Module\X509\Model\Behavior\ExpressionInjector;
use ipl\Orm\Behavior\Binary;
use ipl\Orm\Behavior\BoolCast;
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
                'CASE WHEN UNIX_TIMESTAMP() > %1$s THEN 0 ELSE (%1$s - UNIX_TIMESTAMP()) / 86400 END',
                ['valid_to']
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

    /**
     * Get list of allowed columns to be exported
     *
     * @return string[]
     */
    public function getExportableColumns(): array
    {
        return [
            'id',
            'subject',
            'issuer',
            'version',
            'self_signed',
            'ca',
            'trusted',
            'pubkey_algo',
            'pubkey_bits',
            'signature_algo',
            'signature_hash_algo',
            'valid_from',
            'valid_to'
        ];
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

        $behaviors->add(new DERBase64(['certificate']));

        $behaviors->add(new BoolCast([
            'ca',
            'trusted',
            'self_signed'
        ]));

        $behaviors->add(new ExpressionInjector('duration', 'expires'));
    }

    public function createRelations(Relations $relations)
    {
        $relations->belongsTo('issuer_certificate', static::class)
            ->setForeignKey('subject_hash')
            ->setCandidateKey('issuer_hash');
        $relations->belongsToMany('chain', X509CertificateChain::class)
            ->through(X509CertificateChainLink::class)
            ->setForeignKey('certificate_id');

        $relations->hasMany('certificate', static::class)
            ->setForeignKey('issuer_hash')
            ->setCandidateKey('subject_hash');
        $relations->hasMany('alt_name', X509CertificateSubjectAltName::class)
            ->setJoinType('LEFT');
        $relations->hasMany('dn', X509Dn::class)
            ->setForeignKey('hash')
            ->setCandidateKey('subject_hash')
            ->setJoinType('LEFT');
    }
}
