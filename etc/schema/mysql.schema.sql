-- X509 module | (c) 2018 Icinga Development Team | GPLv2+

create table certificate
(
  id int unsigned auto_increment primary key,
  certificate blob not null,
  fingerprint binary(32) not null,
  version smallint not null,
  pubkey_algo enum('unknown', 'RSA', 'DSA', 'DH', 'EC') not null,
  pubkey_bits smallint(6) not null,
  signature_algo varchar(255) not null,
  signature_hash_algo varchar(255) not null,
  valid_start bigint not null,
  valid_end bigint not null,
  constraint certificate_uk_fingerprint unique (fingerprint)
) engine=InnoDB charset=utf8;

create table certificate_chain
(
  id int unsigned auto_increment primary key,
  ip binary(16) not null,
  port smallint(6) not null,
  sni_name varchar(255) not null,
  latest_log_id int,
  constraint certificate_chain_uk_ip_port_sni_name unique (ip, port, sni_name)
) engine = InnoDB charset = utf8;

create table certificate_chain_log
(
  id int unsigned auto_increment primary key,
  certificate_chain_id int unsigned not null,
  length smallint(6) not null,
  ctime timestamp not null default CURRENT_TIMESTAMP
) engine=InnoDB charset=utf8;

create table certificate_chain_link
(
  certificate_chain_log_id int unsigned not null,
  `order` tinyint not null,
  certificate_id int unsigned not null,
  primary key (certificate_chain_log_id, `order`),
  constraint certificate_chain_link_fk_certificate_chain_id foreign key (certificate_chain_log_id) references certificate_chain_log (id),
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

create table ip_range
(
  id        int unsigned auto_increment primary key,
  ip        binary(16) not null,
  host_bits tinyint    not null,
  constraint ip_range_uk_ip_host_bits unique (ip, host_bits)
) engine=InnoDB charset=utf8;

create table port_range
(
  ip_range_id int unsigned not null,
  start smallint(6) not null,
  end smallint(6) not null,
  primary key (ip_range_id, start),
  constraint port_range_fk_ip_range_id foreign key (ip_range_id) references ip_range (id)
) engine=InnoDB charset=utf8;