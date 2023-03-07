ALTER TABLE x509_target DROP INDEX x509_idx_target_ip_port_hostname;
ALTER TABLE x509_target ADD INDEX x509_idx_target_ip_port (ip, port);
ALTER TABLE x509_certificate MODIFY COLUMN valid_from bigint(20) NOT NULL;
ALTER TABLE x509_certificate MODIFY COLUMN valid_to bigint(20) NOT NULL;
