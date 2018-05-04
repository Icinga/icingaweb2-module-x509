# Icinga Web 2 X.509 Module | (c) 2018 Icinga Development Team | GPLv2+

CREATE TABLE x509_certificate (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  subject varchar(255) NOT NULL, -- CN of the subject DN if present else full subject DN
  subject_hash binary(32) NOT NULL, -- sha256 hash of the full subject DN
  issuer varchar(255) NOT NULL, -- CN of the issuer DN if present else full issuer DN
  issuer_hash binary(32) NOT NULL, -- sha256 hash of the full issuer DN
  issuer_certificate_id int(10) unsigned DEFAULT NULL,
  version enum('1','2','3') NOT NULL,
  self_signed enum('yes','no') NOT NULL DEFAULT 'no',
  ca enum('yes','no') NOT NULL,
  trusted enum('yes','no') NOT NULL DEFAULT 'no',
  pubkey_algo enum('unknown','RSA','DSA','DH','EC') NOT NULL,
  pubkey_bits smallint(6) unsigned NOT NULL,
  signature_algo varchar(255) NOT NULL,
  signature_hash_algo varchar(255) NOT NULL,
  valid_from bigint(20) unsigned NOT NULL,
  valid_to bigint(20) unsigned NOT NULL,
  fingerprint binary(32) NOT NULL, -- sha256 hash
  serial blob NOT NULL,
  certificate blob NOT NULL, -- DER encoded certificate
  ctime timestamp NULL DEFAULT NULL,
  mtime timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY x509_idx_certificate_fingerprint (fingerprint),
  CONSTRAINT x509_fk_certificate_issuer_certificate_id
  FOREIGN KEY (issuer_certificate_id)
  REFERENCES x509_certificate (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

create table x509_target
(
  id int unsigned auto_increment primary key,
  ip binary(16) not null,
  port smallint(6) not null,
  sni_name varchar(255) not null,
  latest_certificate_chain_id int,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  mtime timestamp null default null on update CURRENT_TIMESTAMP,
  constraint certificate_chain_uk_ip_port_sni_name unique (ip, port, sni_name)
) engine = InnoDB charset = utf8mb4;

create table x509_certificate_chain
(
  id int unsigned auto_increment primary key,
  target_id int unsigned not null,
  length smallint(6) not null,
  ctime timestamp not null default CURRENT_TIMESTAMP
) engine=InnoDB charset=utf8mb4;

create table x509_certificate_chain_link
(
  certificate_chain_id int unsigned not null,
  `order` tinyint not null,
  certificate_id int unsigned not null,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  primary key (certificate_chain_id, `order`),
  constraint certificate_chain_link_fk_certificate_chain_id foreign key (certificate_chain_id) references x509_certificate_chain (id),
  constraint certificate_chain_link_fk_certificate_id foreign key (certificate_id) references x509_certificate (id)
) engine=InnoDB charset=utf8mb4;

create index certificate_chain_link_fk_certificate_id on x509_certificate_chain_link (certificate_id);

create table x509_dn
(
  hash binary(32),
  `key` varchar(255) not null,
  value varchar(255) not null,
  `order` tinyint not null,
  type enum('issuer', 'subject') not null,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  primary key (hash, `order`, type)
) engine=InnoDB charset=utf8mb4;

create table x509_certificate_subject_alt_name
(
  certificate_id int unsigned not null,
  type varchar(255) not null,
  value varchar(255) not null,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  primary key (certificate_id, type, value),
  constraint certificate_subject_alt_name_fk_certificate_id foreign key (certificate_id) references x509_certificate (id) on update cascade on delete cascade
) engine=InnoDB charset=utf8mb4;

create table x509_job_run
(
  id int unsigned auto_increment primary key,
  name varchar(255) not null,
  total_targets int(11) not null default 0,
  finished_targets int(11) not null default 0,
  start_time timestamp not null default CURRENT_TIMESTAMP,
  end_time timestamp null,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  mtime timestamp null default null on update CURRENT_TIMESTAMP
) engine=InnoDB charset=utf8mb4;
