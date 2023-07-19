CREATE TABLE x509_job (
  id serial PRIMARY KEY,
  name varchar(255) NOT NULL,
  author varchar(255) NOT NULL,
  cidrs text NOT NULL,
  ports text NOT NULL,
  exclude_targets text DEFAULT NULL,
  ctime bigint NOT NULL,
  mtime bigint NOT NULL,

  UNIQUE (name)
);

CREATE TABLE x509_schedule (
  id serial PRIMARY KEY,
  job_id int NOT NULL,
  name varchar(255) NOT NULL,
  author varchar(255) NOT NULL,
  config text NOT NULL, -- json
  ctime bigint NOT NULL,
  mtime bigint NOT NULL,

  CONSTRAINT fk_x509_schedule_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE
);

DELETE FROM x509_job_run;
ALTER TABLE x509_job_run
  ADD COLUMN job_id int NOT NULL,
  ADD COLUMN schedule_id int DEFAULT NULL,
  DROP COLUMN name,
  DROP COLUMN ctime,
  DROP COLUMN mtime;
ALTER TABLE x509_job_run
  ADD CONSTRAINT fk_x509_job_run_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_x509_job_run_schedule FOREIGN KEY (schedule_id) REFERENCES x509_schedule (id) ON DELETE CASCADE;

CREATE TABLE x509_schema (
  id serial,
  version varchar(64) NOT NULL,
  timestamp bigint NOT NULL,
  success boolenum DEFAULT NULL,
  reason text DEFAULT NULL,

  CONSTRAINT pk_x509_schema PRIMARY KEY (id),
  CONSTRAINT idx_x509_schema_version UNIQUE (version)
);

INSERT INTO x509_schema (version, timestamp, success, reason)
  VALUES ('1.3.0', UNIX_TIMESTAMP() * 1000, 'y', NULL);
