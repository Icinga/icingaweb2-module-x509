CREATE TABLE IF NOT EXISTS x509_job (
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

CREATE TABLE IF NOT EXISTS x509_schedule (
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
  ADD COLUMN IF NOT EXISTS job_id int(10) unsigned NOT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS schedule_id int(10) unsigned DEFAULT NULL AFTER job_id,
  DROP COLUMN IF EXISTS `name`,
  DROP COLUMN IF EXISTS ctime,
  DROP COLUMN IF EXISTS mtime,
  DROP CONSTRAINT IF EXISTS fk_x509_job_run_job,
  DROP CONSTRAINT IF EXISTS fk_x509_job_run_schedule;
ALTER TABLE x509_job_run
  ADD CONSTRAINT fk_x509_job_run_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_x509_job_run_schedule FOREIGN KEY (schedule_id) REFERENCES x509_schedule (id) ON DELETE CASCADE;
