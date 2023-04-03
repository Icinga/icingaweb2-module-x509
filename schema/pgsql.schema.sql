CREATE DOMAIN uint2 AS int4
    CHECK(VALUE >= 0 AND VALUE < 65536);
CREATE DOMAIN biguint AS bigint CONSTRAINT positive CHECK ( VALUE IS NULL OR 0 <= VALUE );
CREATE TYPE boolenum AS ENUM ('n', 'y');
CREATE TYPE certificate_version AS ENUM('1','2','3');
CREATE TYPE dn_type AS ENUM('issuer','subject');
CREATE TYPE pubkey_algo AS ENUM('unknown','RSA','DSA','DH','EC');

-- Used when sorting certificates by expiration date.
CREATE OR REPLACE FUNCTION UNIX_TIMESTAMP(datetime timestamptz DEFAULT NOW())
    RETURNS biguint
    LANGUAGE plpgsql
    PARALLEL SAFE
    AS $$
BEGIN
    RETURN EXTRACT(EPOCH FROM datetime);
END;
$$;

-- IPL ORM renders SQL queries with LIKE operators for all suggestions in the search bar,
-- which fails for numeric and enum types on PostgreSQL. Just like in Icinga DB Web.
CREATE OR REPLACE FUNCTION anynonarrayliketext(anynonarray, text)
  RETURNS bool
  LANGUAGE plpgsql
  IMMUTABLE
  PARALLEL SAFE
  AS $$
BEGIN
    RETURN $1::TEXT LIKE $2;
END;
$$;
CREATE OPERATOR ~~ (LEFTARG=anynonarray, RIGHTARG=text, PROCEDURE=anynonarrayliketext);

CREATE TABLE x509_certificate (
  id serial PRIMARY KEY,
  subject varchar(255) NOT NULL,
  subject_hash bytea NOT NULL,
  issuer varchar(255) NOT NULL,
  issuer_hash bytea NOT NULL,
  issuer_certificate_id int DEFAULT NULL,
  version certificate_version NOT NULL,
  self_signed boolenum NOT NULL DEFAULT 'n',
  ca boolenum NOT NULL,
  trusted boolenum NOT NULL DEFAULT 'n',
  pubkey_algo pubkey_algo NOT NULL,
  pubkey_bits uint2 NOT NULL,
  signature_algo varchar(255) NOT NULL,
  signature_hash_algo varchar(255) NOT NULL,
  valid_from biguint NOT NULL,
  valid_to biguint NOT NULL,
  fingerprint bytea NOT NULL,
  serial bytea NOT NULL,
  certificate bytea NOT NULL,
  ctime biguint NOT NULL,
  mtime biguint DEFAULT NULL,
  CONSTRAINT x509_idx_certificate_fingerprint UNIQUE(fingerprint),
  CONSTRAINT x509_fk_certificate_issuer_certificate_id FOREIGN KEY (issuer_certificate_id) REFERENCES x509_certificate (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE x509_certificate_chain (
  id serial PRIMARY KEY,
  target_id int NOT NULL,
  length uint2 NOT NULL,
  valid boolenum NOT NULL DEFAULT 'n',
  invalid_reason varchar(255) NULL DEFAULT NULL,
  ctime biguint NOT NULL
);

CREATE TABLE x509_certificate_chain_link (
  certificate_chain_id int NOT NULL,
  certificate_id int NOT NULL,
  "order" uint2 NOT NULL,
  ctime biguint NOT NULL,
  PRIMARY KEY(certificate_chain_id,certificate_id,"order"),
  CONSTRAINT x509_fk_certificate_chain_link_certificate_chain_id FOREIGN KEY (certificate_chain_id) REFERENCES x509_certificate_chain (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT x509_fk_certificate_chain_link_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE x509_certificate_subject_alt_name (
  certificate_id int NOT NULL,
  hash bytea NOT NULL,
  type varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  ctime biguint NOT NULL,
  PRIMARY KEY (certificate_id,hash),
  CONSTRAINT x509_fk_certificate_subject_alt_name_certificate_id FOREIGN KEY (certificate_id) REFERENCES x509_certificate (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE x509_dn (
  hash bytea NOT NULL,
  type dn_type NOT NULL,
  "order" uint2 NOT NULL,
  key varchar(255) NOT NULL,
  value varchar(255) NOT NULL,
  ctime biguint NOT NULL,
  PRIMARY KEY (hash,type,"order")
);

CREATE TABLE x509_job_run (
  id serial PRIMARY KEY,
  name varchar(255) NOT NULL,
  total_targets int NOT NULL,
  finished_targets int NOT NULL,
  start_time biguint NULL DEFAULT NULL,
  end_time biguint NULL DEFAULT NULL,
  ctime biguint NOT NULL,
  mtime biguint DEFAULT NULL
);

CREATE TABLE x509_target (
  id serial PRIMARY KEY,
  ip bytea NOT NULL,
  port uint2 NOT NULL,
  hostname varchar(255) NULL DEFAULT NULL,
  latest_certificate_chain_id int NULL DEFAULT NULL,
  last_scan biguint NOT NULL,
  ctime biguint NOT NULL,
  mtime biguint DEFAULT NULL
);

CREATE INDEX x509_idx_target ON x509_target (ip,port,hostname);
