ALTER TABLE x509_certificate
    CHANGE self_signed self_signed enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n',
    CHANGE ca ca enum('n', 'y', 'yes', 'no') NOT NULL,
    CHANGE trusted trusted enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n';

UPDATE x509_certificate SET self_signed = 'y' WHERE self_signed = 'yes';
UPDATE x509_certificate SET self_signed = 'n' WHERE self_signed = 'no';

UPDATE x509_certificate SET ca = 'y' WHERE ca = 'yes';
UPDATE x509_certificate SET ca = 'n' WHERE ca = 'no';

UPDATE x509_certificate SET trusted = 'y' WHERE trusted = 'yes';
UPDATE x509_certificate SET trusted = 'n' WHERE trusted = 'no';

ALTER TABLE x509_certificate
    CHANGE self_signed self_signed enum('n', 'y') NOT NULL DEFAULT 'n',
    CHANGE ca ca enum('n', 'y') NOT NULL,
    CHANGE trusted trusted enum('n', 'y') NOT NULL DEFAULT 'n';

ALTER TABLE x509_certificate_chain CHANGE valid valid enum('n', 'y', 'yes', 'no') NOT NULL DEFAULT 'n';

UPDATE x509_certificate_chain SET valid = 'y' WHERE valid = 'yes';
UPDATE x509_certificate_chain SET valid = 'n' WHERE valid = 'no';

ALTER TABLE x509_certificate_chain CHANGE valid valid enum('n', 'y') NOT NULL DEFAULT 'n';

ALTER TABLE x509_target ADD COLUMN last_scan bigint unsigned NOT NULL DEFAULT UNIX_TIMESTAMP() AFTER latest_certificate_chain_id;
ALTER TABLE x509_target MODIFY COLUMN last_scan bigint unsigned NOT NULL;
