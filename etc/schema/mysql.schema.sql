-- X509 module | (c) 2018 Icinga Development Team | GPLv2+

CREATE TABLE `certificate`(
  `id`                  BIGINT            NOT NULL AUTO_INCREMENT,
  `der`                 BLOB  NOT NULL,
  `der_sha512_sum`      BINARY(64)        NOT NULL,
  `pubkey_algo`         VARCHAR(255)      NOT NULL,
  `pubkey_bits`         SMALLINT          NOT NULL,
  `signature_algo`      VARCHAR(255)      NOT NULL,
  `signature_hash_algo` VARCHAR(255)      NOT NULL,
  `valid_start`         DATETIME         NOT NULL,
  `valid_end`           DATETIME         NOT NULL,
  CONSTRAINT `certificate_pk` PRIMARY KEY (`id`),
  CONSTRAINT `certificate_uk_der_sha512_sum` UNIQUE (`der_sha512_sum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_issuer` (
  `certificate_id`  BIGINT  NOT NULL,
  `issuer_id`       BIGINT  NOT NULL,
  CONSTRAINT `certificate_issuer_pk` PRIMARY KEY (`certificate_id`, `issuer_id`),
  CONSTRAINT `certificate_issuer_fk_certificate_id` FOREIGN KEY (`certificate_id`) REFERENCES `certificate`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `certificate_issuer_fk_issuer_id` FOREIGN KEY (`issuer_id`) REFERENCES `certificate`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_subject_dn` (
  `certificate_id`  BIGINT        NOT NULL,
  `key`             VARCHAR(255)  NOT NULL,
  `value`           VARCHAR(255)  NOT NULL,
  `order`           TINYINT NOT NULL,
  CONSTRAINT `certificate_subject_dn_pk` PRIMARY KEY (`certificate_id`, `order`),
  CONSTRAINT `certificate_subject_dn_fk_certificate_id` FOREIGN KEY (`certificate_id`) REFERENCES `certificate`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_issuer_dn` (
  `certificate_id`  BIGINT        NOT NULL,
  `key`             VARCHAR(255)  NOT NULL,
  `value`           VARCHAR(255)  NOT NULL,
  `order`           TINYINT NOT NULL,
  CONSTRAINT `certificate_issuer_dn_pk` PRIMARY KEY (`certificate_id`, `order`),
  CONSTRAINT `certificate_issuer_dn_fk_certificate_id` FOREIGN KEY (`certificate_id`) REFERENCES `certificate`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_subject_alt_name` (
  `certificate_id`  BIGINT        NOT NULL,
  `type`            VARCHAR(255)  NOT NULL,
  `value`           VARCHAR(255)  NOT NULL,
  CONSTRAINT `certificate_subject_alt_name_pk` PRIMARY KEY (`certificate_id`, `type`, `value`),
  CONSTRAINT `certificate_subject_alt_name_fk_certificate_id` FOREIGN KEY (`certificate_id`) REFERENCES `certificate`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_chain` (
  `id`        BIGINT        NOT NULL AUTO_INCREMENT,
  `ip`        BINARY(16)    NOT NULL,
  `port`      SMALLINT      NOT NULL,
  `sni_name`  VARCHAR(255)  NOT NULL,
  `ctime`     TIMESTAMP     NOT NULL,
  CONSTRAINT `certificate_chain_pk` PRIMARY KEY (`id`),
  CONSTRAINT `certificate_chain_uk_ip_port_sni_name_ctime` UNIQUE KEY (`ip`, `port`, `sni_name`, `ctime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `certificate_chain_link` (
  `certificate_chain_id`  BIGINT  NOT NULL,
  `order`                 TINYINT NOT NULL,
  `certificate_id`        BIGINT  NOT NULL,
  CONSTRAINT `certificate_chain_link_pk` PRIMARY KEY (`certificate_chain_id`, `order`),
  CONSTRAINT `certificate_chain_link_fk_certificate_chain_id` FOREIGN KEY (`certificate_chain_id`) REFERENCES `certificate_chain`(`id`),
  CONSTRAINT `certificate_chain_link_fk_certificate_id` FOREIGN KEY (`certificate_id`) REFERENCES `certificate`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `ip_range` (
  `id`        BIGINT      NOT NULL AUTO_INCREMENT,
  `ip`        BINARY(16)  NOT NULL,
  `host_bits` TINYINT     NOT NULL,
  CONSTRAINT `ip_range_pk` PRIMARY KEY (`id`),
  CONSTRAINT `ip_range_uk_ip_host_bits` UNIQUE KEY (`ip`, `host_bits`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `port_range` (
  `ip_range_id` BIGINT        NOT NULL,
  `start`       SMALLINT      NOT NULL,
  `end`         SMALLINT      NOT NULL,
  CONSTRAINT `port_range_pk` PRIMARY KEY (`ip_range_id`, `start`),
  CONSTRAINT `port_range_fk_ip_range_id` FOREIGN KEY (`ip_range_id`) REFERENCES `ip_range`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
