ALTER TABLE x509_certificate
    MODIFY self_signed enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n',
    MODIFY ca enum('n', 'y', 'yes', 'no') NOT NULL,
    MODIFY trusted enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n',
    ADD COLUMN ctime_tmp bigint unsigned DEFAULT NULL,
    ADD COLUMN mtime_tmp bigint unsigned DEFAULT NULL;

UPDATE x509_certificate SET self_signed = 'y' WHERE self_signed = 'yes';
UPDATE x509_certificate SET self_signed = 'n' WHERE self_signed = 'no';

UPDATE x509_certificate SET ca = 'y' WHERE ca = 'yes';
UPDATE x509_certificate SET ca = 'n' WHERE ca = 'no';

UPDATE x509_certificate SET trusted = 'y' WHERE trusted = 'yes';
UPDATE x509_certificate SET trusted = 'n' WHERE trusted = 'no';

UPDATE x509_certificate SET mtime_tmp = UNIX_TIMESTAMP(mtime) * 1000.0, ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0;

ALTER TABLE x509_certificate
    MODIFY self_signed enum('n', 'y') NOT NULL DEFAULT 'n',
    MODIFY ca enum('n', 'y') NOT NULL,
    MODIFY trusted enum('n', 'y') NOT NULL DEFAULT 'n',
    DROP COLUMN mtime,
    DROP COLUMN ctime,
    CHANGE COLUMN ctime_tmp ctime bigint unsigned DEFAULT NULL,
    CHANGE COLUMN mtime_tmp mtime bigint unsigned DEFAULT NULL;

ALTER TABLE x509_certificate_chain
    CHANGE valid valid enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n',
    ADD COLUMN ctime_tmp bigint unsigned NOT NULL;

UPDATE x509_certificate_chain SET valid = 'y' WHERE valid = 'yes';
UPDATE x509_certificate_chain SET valid = 'n' WHERE valid = 'no';

UPDATE x509_certificate_chain SET ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0;

ALTER TABLE x509_certificate_chain
    MODIFY valid enum('n', 'y') NOT NULL DEFAULT 'n',
    DROP ctime,
    CHANGE ctime_tmp ctime bigint unsigned NOT NULL;

ALTER TABLE x509_certificate_chain_link ADD COLUMN ctime_tmp bigint unsigned NOT NULL;

UPDATE x509_certificate_chain_link SET ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0;

ALTER TABLE x509_certificate_chain_link
    DROP COLUMN ctime,
    CHANGE ctime_tmp ctime bigint unsigned NOT NULL;

ALTER TABLE x509_certificate_subject_alt_name ADD COLUMN ctime_tmp bigint unsigned NOT NULL;

UPDATE x509_certificate_subject_alt_name SET ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0;

ALTER TABLE x509_certificate_subject_alt_name
    DROP COLUMN ctime,
    CHANGE ctime_tmp ctime bigint unsigned NOT NULL;

ALTER TABLE x509_dn ADD COLUMN ctime_tmp bigint unsigned NOT NULL;

UPDATE x509_dn SET ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0;

ALTER TABLE x509_dn
    DROP COLUMN ctime,
    CHANGE ctime_tmp ctime bigint unsigned NOT NULL;

ALTER TABLE x509_job_run
    ADD COLUMN starttime_tmp bigint unsigned DEFAULT NULL,
    ADD COLUMN endtime_tmp bigint unsigned DEFAULT NULL,
    ADD COLUMN ctime_tmp bigint unsigned DEFAULT NULL,
    ADD COLUMN mtime_tmp bigint unsigned DEFAULT NULL;

UPDATE x509_job_run SET
    starttime_tmp = UNIX_TIMESTAMP(start_time) * 1000.0,
    endtime_tmp = UNIX_TIMESTAMP(end_time) * 1000.0,
    ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0,
    mtime_tmp = UNIX_TIMESTAMP(mtime) * 1000.0;

ALTER TABLE x509_job_run
    DROP COLUMN start_time,
    DROP COLUMN end_time,
    DROP COLUMN mtime,
    DROP COLUMN ctime,
    CHANGE starttime_tmp start_time bigint unsigned DEFAULT NULL,
    CHANGE endtime_tmp end_time bigint unsigned DEFAULT NULL,
    CHANGE ctime_tmp ctime bigint unsigned DEFAULT NULL,
    CHANGE mtime_tmp mtime bigint unsigned DEFAULT NULL;

ALTER TABLE x509_target ADD COLUMN last_scan bigint unsigned DEFAULT NULL AFTER latest_certificate_chain_id;
UPDATE x509_target SET last_scan = UNIX_TIMESTAMP() * 1000.0;
ALTER TABLE x509_target MODIFY COLUMN last_scan bigint unsigned NOT NULL;

ALTER TABLE x509_target
    ADD COLUMN ctime_tmp bigint unsigned DEFAULT NULL,
    ADD COLUMN mtime_tmp bigint unsigned DEFAULT NULL;

UPDATE x509_target SET ctime_tmp = UNIX_TIMESTAMP(ctime) * 1000.0, mtime_tmp = UNIX_TIMESTAMP(mtime) * 1000.0;

ALTER TABLE x509_target
    DROP COLUMN ctime,
    DROP COLUMN mtime,
    CHANGE ctime_tmp ctime bigint unsigned DEFAULT NULL,
    CHANGE mtime_tmp mtime bigint unsigned DEFAULT NULL;
