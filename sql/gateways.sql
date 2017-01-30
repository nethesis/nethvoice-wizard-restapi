USE asterisk;

DROP TABLE IF EXISTS `gateway_models`;

CREATE TABLE IF NOT EXISTS `gateway_models` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
  `model` varchar(100) NOT NULL default '',
  `manufacturer` varchar(20) default NULL,
  `tech` varchar(20) default NULL,
  `n_pri_trunks` INT UNSIGNED default '0',
  `n_isdn_trunks` INT UNSIGNED default '0',
  `n_fxo_trunks` INT UNSIGNED default '0',
  `n_fxs_ext` INT UNSIGNED default '0',
  `description` varchar(50) default NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `gateway_models` WRITE;
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4552','Patton','isdn',0,1,0,0,'ISDN 1 Porta');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4552','Patton','isdn',0,1,0,0,'ISDN 1 Porta (+ 1 TE)');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4554','Patton','isdn',0,2,0,0,'ISDN 2 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4638','Patton','isdn',0,4,0,0,'ISDN 4 Porte (4 chiamate voip)');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4638','Patton','isdn',0,4,0,0,'ISDN 4 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661','Patton','isdn',0,8,0,0,'ISDN 8 Porte (8 chiamate voip)');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661','Patton','isdn',0,8,0,0,'ISDN 8 Porte (16 chiamate voip)');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4112fxs','Patton','fxs',0,0,0,2,'Analogico 2 Porte FXS');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4112fxo','Patton','fxo',0,0,2,0,'Analogico 2 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4114fxs_fxo','Patton','fxo_fxs',0,0,2,2,'Analogico 2 Porte FXS + 2 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4114fxs','Patton','fxs',0,0,0,4,'Analogico 4 Porte FXS');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4114fxo','Patton','fxo',0,0,4,0,'Analogico 4 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4526fxs','Patton','fxs',0,0,0,6,'Analogico 6 Porte FXS');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4118fxs','Patton','fxs',0,0,0,8,'Analogico 8 Porte FXS');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4116fxs4_fxo2','Patton','fxo_fxs',0,0,2,4,'Analogico 4 Porte FXS + 2 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4118fxs_fxo','Patton','fxo_fxs',0,0,4,4,'Analogico 4 Porte FXS + 4 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661fxs4_isdn2','Patton','fxs_isdn',0,2,0,4,'Analogico 4 Porte FXS + 2 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661fxs4_isdn4','Patton','fxs_isdn',0,4,0,4,'Analogico 4 Porte FXS + 4 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661fxs2_fxo2_isdn2','Patton','fxo_fxs_isdn',0,2,2,2,'Analogico 2 Porte FXS + 2 Porte FXO + 2 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661fxs4_fxo4_isdn4','Patton','fxo_fxs_isdn',0,4,4,4,'Analogico 4 Porte FXS + 4 Porte FXO + 4 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4661fxs4_isdn8','Patton','fxs_isdn',0,8,0,4,'Analogico 4 Porte FXS + 8 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4671fxs8_isdn4','Patton','fxs_isdn',0,4,0,8,'Analogico 8 Porte FXS + 4 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4970','Patton','pri',1,0,0,0,'PRI 1 Porta');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('4970_4','Patton','pri',4,0,0,0,'PRI 4 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_ISDN_1','Patton','isdn',0,1,0,0, 'TRINITY ISDN 1 Porta');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_ISDN_2','Patton','isdn',0,2,0,0, 'TRINITY ISDN 2 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_ISDN_4','Patton','isdn',0,4,0,0, 'TRINITY ISDN 4 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_PRI_1','Patton','pri',1,0,0,0, 'TRINITY PRI 1 Porta');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_PRI_2','Patton','pri',2,0,0,0, 'TRINITY PRI 2 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('TRI_PRI_4','Patton','pri',4,0,0,0, 'TRINITY PRI 4 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_50_4fxs_2fxo','Sangoma','fxo_fxs',0,0,2,4,'Vega 50 4 Porte FXS 2 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_50_24fxs','Sangoma','fxs',0,0,0,24,'Vega 3000 24 Porte FXS');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_50_4fxo','Sangoma','fxo',0,0,4,0,'Vega 50 4 Porte FXO');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_50_2isdn','Sangoma','isdn',0,2,0,0,'Vega 50 2 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_50_4isdn','Sangoma','isdn',0,4,0,0,'Vega 50 4 Porte ISDN');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_100_1pri','Sangoma','pri',1,0,0,0,'Vega 100 1 Porta PRI E1');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('Vega_200_2pri','Sangoma','pri',2,0,0,0,'Vega 200 2 Porte PRI E1');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('M4401','Mediatrix','isdn',0,1,0,0, '4401 ISDN 1 Porta');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('M4402','Mediatrix','isdn',0,2,0,0, '4402 ISDN 2 Porte');
INSERT IGNORE INTO `gateway_models` (`model`, `manufacturer`, `tech`, `n_pri_trunks`, `n_isdn_trunks`, `n_fxo_trunks`, `n_fxs_ext`, `description`) VALUES ('M4404','Mediatrix','isdn',0,4,0,0, '4404 ISDN 4 Porte');

UNLOCK TABLES;

CREATE TABLE IF NOT EXISTS `gateway_config` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `model_id` INT UNSIGNED NOT NULL default 0,
  `name` varchar(100) default NULL,
  `ipv4` varchar(20) default NULL,
  `ipv4_new` varchar(20) default NULL,
  `gateway` varchar(20) default NULL,
  `ipv4_green` varchar(20) default NULL,
  `netmask_green` varchar(20) default NULL,
  `mac` char(18) default NULL,
  UNIQUE `mac_key` (`mac`),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`model_id`) REFERENCES `gateway_models`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gateway_config_fxo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` INT UNSIGNED NOT NULL default '0',
  `trunk` int(11) NOT NULL,
  `number` varchar(100) default NULL,
  `secret` varchar(10) default NULL,
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`config_id`) REFERENCES `gateway_config`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gateway_config_isdn` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` INT UNSIGNED NOT NULL default '0',
  `trunk` int(11) NOT NULL,
  `protocol` varchar(3) default NULL,
  `secret` varchar(10) default NULL,
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`config_id`) REFERENCES `gateway_config`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gateway_config_pri` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` INT UNSIGNED NOT NULL default '0',
  `trunk` int(11) NOT NULL,
  `secret` varchar(10) default NULL,
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`config_id`) REFERENCES `gateway_config`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gateway_config_fxs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` INT UNSIGNED NOT NULL default '0',
  `extension` varchar(100) default NULL,
  `physical_extension` varchar(100) default NULL,
  `secret` varchar(100) default NULL,
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`config_id`) REFERENCES `gateway_config`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


