CREATE DOMAIN uint2 AS int4
    CHECK(VALUE >= 0 AND VALUE < 65536);
CREATE TYPE yesno AS ENUM ('yes','no');
CREATE TYPE certificate_version AS ENUM('1','2','3');
CREATE TYPE dn_type AS ENUM('issuer','subject');
CREATE TYPE pubkey_algo AS ENUM('unknown','RSA','DSA','DH','EC');

CREATE TABLE x509_certificate (
  id serial PRIMARY KEY,
  subject varchar(255) NOT NULL,
  subject_hash bytea NOT NULL,
  issuer varchar(255) NOT NULL,
  issuer_hash bytea NOT NULL,
  issuer_certificate_id int DEFAULT NULL,
  version certificate_version NOT NULL,
  self_signed yesno NOT NULL DEFAULT 'no',
  ca yesno NOT NULL,
  trusted yesno NOT NULL DEFAULT 'no',
  pubkey_algo pubkey_algo NOT NULL,
  pubkey_bits uint2 NOT NULL,
  signature_algo varchar(255) NOT NULL,
  signature_hash_algo varchar(255) NOT NULL,
  valid_from bigint NOT NULL,
  valid_to bigint NOT NULL,
  fingerprint bytea NOT NULL,
  serial bytea NOT NULL,
  certificate bytea NOT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mtime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT x509_idx_certificate_fingerprint UNIQUE(fingerprint),
  CONSTRAINT x509_fk_certificate_issuer_certificate_id FOREIGN KEY (issuer_certificate_id) REFERENCES x509_certificate (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE x509_certificate_chain (
  id serial PRIMARY KEY,
  target_id int NOT NULL,
  length uint2 NOT NULL,
  valid yesno NOT NULL DEFAULT 'no',
  invalid_reason varchar(255) NULL DEFAULT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE x509_certificate_chain_link (
  certificate_chain_id int NOT NULL,
  certificate_id int NOT NULL,
  "order" uint2 NOT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(certificate_chain_id,certificate_id,"order"),
  CONSTRAINT x509_fk_certificate_chain_link_certificate_chain_id FOREIGN KEY (certificate_chain_id) REFERENCES x509_certificate_chain (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT x509_fk_certificate_chain_link_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE x509_certificate_subject_alt_name (
  certificate_id int NOT NULL,
  hash bytea NOT NULL,
  type varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_id,hash),
  CONSTRAINT x509_fk_certificate_subject_alt_name_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE x509_dn (
  hash bytea NOT NULL,
  type dn_type NOT NULL,
  "order" uint2 NOT NULL,
  key varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hash,type,"order")
);

CREATE TABLE x509_job_run (
  id serial PRIMARY KEY,
  name varchar(255) NOT NULL,
  total_targets int NOT NULL,
  finished_targets int NOT NULL,
  start_time timestamptz NULL DEFAULT NULL,
  end_time timestamptz NULL DEFAULT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mtime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE x509_target (
  id serial PRIMARY KEY,
  ip bytea NOT NULL,
  port uint2 NOT NULL,
  hostname varchar(255) NULL DEFAULT NULL,
  latest_certificate_chain_id int NULL DEFAULT NULL,
  ctime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mtime timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX x509_idx_target ON x509_target (ip,port,hostname);
