-- X509 module | (c) 2018 Icinga Development Team | GPLv2+

create table x509_certificate
(
  id int unsigned auto_increment primary key,
  subject varchar(255) not null,
  subject_hash binary(32) not null,
  issuer varchar(255) not null,
  issuer_hash binary(32) not null,
  issuer_certificate_id int unsigned null,
  trusted enum('yes', 'no') not null default 'no',
  self_signed enum('yes', 'no') not null default 'no',
  certificate blob not null,
  fingerprint binary(32) not null,
  version enum('1', '2', '3') not null,
  serial blob not null,
  ca enum('yes', 'no') not null,
  pubkey_algo enum('unknown', 'RSA', 'DSA', 'DH', 'EC') not null,
  pubkey_bits smallint(6) unsigned not null,
  signature_algo varchar(255) not null,
  signature_hash_algo varchar(255) not null,
  valid_start bigint unsigned not null,
  valid_end bigint unsigned not null,
  ctime timestamp not null default CURRENT_TIMESTAMP,
  mtime timestamp null default null on update CURRENT_TIMESTAMP,
  constraint certificate_uk_fingerprint unique (fingerprint),
  constraint certificate_issuer_certificate_id foreign key (issuer_certificate_id) references x509_certificate (id)
) engine=InnoDB charset=utf8mb4;

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