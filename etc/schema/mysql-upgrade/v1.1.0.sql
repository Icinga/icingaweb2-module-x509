ALTER TABLE x509_certificate MODIFY COLUMN valid_from bigint(20) NOT NULL;
ALTER TABLE x509_certificate MODIFY COLUMN valid_to bigint(20) NOT NULL;
