ALTER TABLE x509_target MODIFY COLUMN `port` smallint unsigned NOT NULL;

ALTER TABLE x509_certificate_subject_alt_name DROP FOREIGN KEY x509_fk_certificate_subject_alt_name_certificate_id;

ALTER TABLE x509_certificate_subject_alt_name DROP PRIMARY KEY;

ALTER TABLE x509_certificate_subject_alt_name ADD COLUMN hash binary(32) NOT NULL
  COMMENT 'sha256 hash of type=value'
  AFTER certificate_id;

UPDATE x509_certificate_subject_alt_name SET hash = UNHEX(SHA2(CONCAT(type, '=', value), 256));

ALTER TABLE x509_certificate_subject_alt_name ADD PRIMARY KEY(certificate_id, hash);

ALTER TABLE x509_certificate_subject_alt_name ADD
  CONSTRAINT x509_fk_certificate_subject_alt_name_certificate_id
  FOREIGN KEY (certificate_id)
  REFERENCES x509_certificate (id)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE x509_certificate_subject_alt_name ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=default;

ALTER TABLE x509_target DROP INDEX x509_idx_target_ip_port_hostname;

ALTER TABLE x509_target ADD INDEX x509_idx_target_ip_port_hostname(ip,port,hostname(191));

ALTER TABLE x509_target ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=default;
