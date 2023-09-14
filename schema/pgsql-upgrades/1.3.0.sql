CREATE TABLE IF NOT EXISTS x509_job (
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

CREATE TABLE IF NOT EXISTS x509_schedule (
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
  ADD COLUMN IF NOT EXISTS job_id int NOT NULL,
  ADD COLUMN IF NOT EXISTS schedule_id int DEFAULT NULL,
  DROP COLUMN IF EXISTS name,
  DROP COLUMN IF EXISTS ctime,
  DROP COLUMN IF EXISTS mtime,
  DROP CONSTRAINT IF EXISTS fk_x509_job_run_job,
  DROP CONSTRAINT IF EXISTS fk_x509_job_run_schedule;
ALTER TABLE x509_job_run
  ADD CONSTRAINT fk_x509_job_run_job FOREIGN KEY (job_id) REFERENCES x509_job (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_x509_job_run_schedule FOREIGN KEY (schedule_id) REFERENCES x509_schedule (id) ON DELETE CASCADE;
