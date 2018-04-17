-- X509 module | (c) 2018 Icinga Development Team | GPLv2+

create table certificate
(
  id int unsigned auto_increment primary key,
  name varchar(255) not null,
  certificate blob not null,
  fingerprint binary(32) not null,
  version smallint not null,
  serial blob not null,
  ca tinyint(1) not null,
  pubkey_algo enum('unknown', 'RSA', 'DSA', 'DH', 'EC') not null,
  pubkey_bits smallint(6) not null,
  signature_algo varchar(255) not null,
  signature_hash_algo varchar(255) not null,
  valid_start bigint not null,
  valid_end bigint not null,
  constraint certificate_uk_fingerprint unique (fingerprint)
) engine=InnoDB charset=utf8;

create table target
(
  id int unsigned auto_increment primary key,
  ip binary(16) not null,
  port smallint(6) not null,
  sni_name varchar(255) not null,
  latest_certificate_chain_id int,
  constraint certificate_chain_uk_ip_port_sni_name unique (ip, port, sni_name)
) engine = InnoDB charset = utf8;

create table certificate_chain
(
  id int unsigned auto_increment primary key,
  target_id int unsigned not null,
  length smallint(6) not null,
  ctime timestamp not null default CURRENT_TIMESTAMP
) engine=InnoDB charset=utf8;

create table certificate_chain_link
(
  certificate_chain_id int unsigned not null,
  `order` tinyint not null,
  certificate_id int unsigned not null,
  primary key (certificate_chain_id, `order`),
  constraint certificate_chain_link_fk_certificate_chain_id foreign key (certificate_chain_id) references certificate_chain (id),
  constraint certificate_chain_link_fk_certificate_id foreign key (certificate_id) references certificate (id)
) engine=InnoDB charset=utf8;

create index certificate_chain_link_fk_certificate_id on certificate_chain_link (certificate_id);

create table certificate_issuer_dn
(
  certificate_id int unsigned not null,
  `key` varchar(255) not null,
  value varchar(255) not null,
  `order` tinyint not null,
  primary key (certificate_id, `order`),
  constraint certificate_issuer_dn_fk_certificate_id foreign key (certificate_id) references certificate (id) on update cascade on delete cascade
) engine=InnoDB charset=utf8;

create table certificate_subject_alt_name
(
  certificate_id int unsigned not null,
  type varchar(255) not null,
  value varchar(255) not null,
  primary key (certificate_id, type, value),
  constraint certificate_subject_alt_name_fk_certificate_id foreign key (certificate_id) references certificate (id) on update cascade on delete cascade
) engine=InnoDB charset=utf8;

create table certificate_subject_dn
(
  certificate_id int unsigned not null,
  `key` varchar(255) not null,
  value varchar(255) not null,
  `order` tinyint not null,
  primary key (certificate_id, `order`),
  constraint certificate_subject_dn_fk_certificate_id foreign key (certificate_id) references certificate (id) on update cascade on delete cascade
) engine=InnoDB charset=utf8;

create table job_run
(
  id int unsigned auto_increment primary key,
  name varchar(255) not null,
  total_targets int(11) not null default 0,
  finished_targets int(11) not null default 0,
  ctime timestamp not null default CURRENT_TIMESTAMP
) engine=InnoDB charset=utf8;