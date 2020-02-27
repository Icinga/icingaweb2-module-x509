ALTER TABLE x509_target DROP INDEX x509_idx_target_ip_port_hostname;
ALTER TABLE x509_target ADD INDEX x509_idx_target_ip_port (ip, port);
