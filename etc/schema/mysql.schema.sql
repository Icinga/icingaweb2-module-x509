CREATE TABLE x509_certificate (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL COMMENT 'CN of the subject DN if present else full subject DN',
  subject_hash binary(32) NOT NULL COMMENT 'sha256 hash of the full subject DN',
  `issuer` varchar(255) NOT NULL COMMENT 'CN of the issuer DN if present else full issuer DN',
  issuer_hash binary(32) NOT NULL COMMENT 'sha256 hash of the full issuer DN',
  issuer_certificate_id int(10) unsigned DEFAULT NULL,
  version enum('1','2','3') NOT NULL,
  self_signed enum('n', 'y') NOT NULL DEFAULT 'n',
  ca enum('n', 'y') NOT NULL,
  trusted enum('n', 'y') NOT NULL DEFAULT 'n',
  pubkey_algo enum('unknown','RSA','DSA','DH','EC') NOT NULL,
  pubkey_bits smallint(6) unsigned NOT NULL,
  signature_algo varchar(255) NOT NULL,
  signature_hash_algo varchar(255) NOT NULL,
  valid_from bigint(20) NOT NULL,
  valid_to bigint(20) NOT NULL,
  fingerprint binary(32) NOT NULL COMMENT 'sha256 hash',
  `serial` blob NOT NULL,
  certificate blob NOT NULL COMMENT 'DER encoded certificate',
  ctime timestamp NULL DEFAULT NULL,
  mtime timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY x509_idx_certificate_fingerprint (fingerprint),
  KEY x509_fk_certificate_issuer_certificate_id (issuer_certificate_id),
  CONSTRAINT x509_fk_certificate_issuer_certificate_id FOREIGN KEY (issuer_certificate_id) REFERENCES x509_certificate (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_certificate_chain (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  target_id int(10) unsigned NOT NULL,
  length smallint(6) NOT NULL,
  valid enum('n', 'y') NOT NULL DEFAULT 'n',
  invalid_reason varchar(255) NULL DEFAULT NULL,
  ctime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_certificate_chain_link (
  certificate_chain_id int(10) unsigned NOT NULL,
  certificate_id int(10) unsigned NOT NULL,
  `order` tinyint(4) NOT NULL,
  ctime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_chain_id,certificate_id,`order`),
  KEY x509_fk_certificate_chain_link_certificate_id (certificate_id),
  CONSTRAINT x509_fk_certificate_chain_link_certificate_chain_id FOREIGN KEY (certificate_chain_id) REFERENCES x509_certificate_chain (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT x509_fk_certificate_chain_link_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_certificate_subject_alt_name (
  certificate_id int(10) unsigned NOT NULL,
  hash binary(32) NOT NULL COMMENT 'sha256 hash of type=value',
  `type` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  ctime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_id,hash),
  CONSTRAINT x509_fk_certificate_subject_alt_name_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_dn (
  `hash` binary(32) NOT NULL,
  `type` enum('issuer','subject') NOT NULL,
  `order` tinyint(4) unsigned NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  ctime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hash`,`type`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_job_run (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  total_targets int(10) NOT NULL,
  finished_targets int(10) NOT NULL,
  start_time timestamp NULL DEFAULT NULL,
  end_time timestamp NULL DEFAULT NULL,
  ctime timestamp NULL DEFAULT NULL,
  mtime timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_target (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  ip binary(16) NOT NULL,
  `port` smallint unsigned NOT NULL,
  hostname varchar(255) NULL DEFAULT NULL,
  latest_certificate_chain_id int(10) unsigned NULL DEFAULT NULL,
  last_scan bigint unsigned NOT NULL,
  ctime timestamp NULL DEFAULT NULL,
  mtime timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX x509_idx_target_ip_port (ip, port)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
