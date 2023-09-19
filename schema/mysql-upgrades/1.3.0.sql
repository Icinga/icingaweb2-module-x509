CREATE TABLE x509_job (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  author varchar(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  cidrs text NOT NULL,
  ports text NOT NULL,
  exclude_targets text DEFAULT NULL,
  ctime bigint unsigned NOT NULL,
  mtime bigint unsigned NOT NULL,

  PRIMARY KEY (id),
  UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE x509_schedule (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  job_id int(10) unsigned NOT NULL,
  name varchar(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  author varchar(255) NOT NULL COLLATE utf8mb4_unicode_ci,
  config text NOT NULL, -- json
  ctime bigint unsigned NOT NULL,
  mtime bigint unsigned NOT NULL,

  PRIMARY KEY (id),
  CONSTRAINT fk_x509_schedule_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELETE FROM x509_job_run;
ALTER TABLE x509_job_run
  ADD COLUMN job_id int(10) unsigned NOT NULL AFTER id,
  ADD COLUMN schedule_id int(10) unsigned DEFAULT NULL AFTER job_id,
  DROP COLUMN `name`,
  DROP COLUMN ctime,
  DROP COLUMN mtime;
ALTER TABLE x509_job_run
  ADD CONSTRAINT fk_x509_job_run_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_x509_job_run_schedule FOREIGN KEY (schedule_id) REFERENCES x509_schedule (id) ON DELETE CASCADE;

CREATE TABLE x509_schema (
  id int unsigned NOT NULL AUTO_INCREMENT,
  version varchar(64) NOT NULL,
  timestamp bigint unsigned NOT NULL,
  success enum ('n', 'y') DEFAULT NULL,
  reason text DEFAULT NULL,

  PRIMARY KEY (id),
  CONSTRAINT idx_x509_schema_version UNIQUE (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin ROW_FORMAT=DYNAMIC;

INSERT INTO x509_schema (version, timestamp, success, reason)
  VALUES ('1.3.0', UNIX_TIMESTAMP() * 1000, 'y', NULL);
