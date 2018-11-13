USE asterisk;

CREATE TABLE IF NOT EXISTS `rest_cti_profiles_paramurl`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `profile_id` INT UNSIGNED NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  UNIQUE `profile_id_key` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_profiles`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL DEFAULT 'Custom'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_macro_permissions`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(190) NOT NULL DEFAULT '',
  `displayname` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(1024) NOT NULL DEFAULT '',
  UNIQUE `name_key` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_permissions`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(190) NOT NULL DEFAULT '',
  `displayname` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(1024) NOT NULL DEFAULT '',
  UNIQUE `name_key` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_profiles_permissions`(
  `profile_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  FOREIGN KEY (`profile_id`) REFERENCES `rest_cti_profiles`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `rest_cti_permissions`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  UNIQUE KEY `line` (`profile_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_profiles_macro_permissions`(
  `profile_id` INT UNSIGNED NOT NULL,
  `macro_permission_id` INT UNSIGNED NOT NULL,
  FOREIGN KEY (`profile_id`) REFERENCES `rest_cti_profiles`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (`macro_permission_id`) REFERENCES `rest_cti_macro_permissions`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  UNIQUE KEY `line` (`profile_id`,`macro_permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*This table contains all available permissions inside macro permissions*/
CREATE TABLE IF NOT EXISTS `rest_cti_macro_permissions_permissions`(
  `macro_permission_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  FOREIGN KEY (`macro_permission_id`) REFERENCES `rest_cti_macro_permissions`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `rest_cti_permissions`(`id`)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  UNIQUE KEY `line` (`macro_permission_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(65) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_key` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_users_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group` (`user_id`,`group_id`),
  KEY `group_id` (`group_id`),
  FOREIGN KEY (`group_id`) REFERENCES `rest_cti_groups` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `userman_users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_streaming` (
  `descr` varchar(50) NOT NULL,
  `url` varchar(8000) NOT NULL DEFAULT 'localhost',
  `user` varchar(30) DEFAULT '',
  `secret` varchar(90) DEFAULT '',
  `frame-rate` int(11) DEFAULT '1000',
  `exten` int(11) DEFAULT NULL,
  `open` varchar(10) DEFAULT '',
  PRIMARY KEY (`descr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/*Permissions updates*/
UPDATE IGNORE `rest_cti_permissions` SET `name`='advanced_off_hour_tmp',`displayname`='Advanced Off Hour',`description`='Allow to change user\'s incoming call path and generic inbound routes' WHERE id = 25;
UPDATE IGNORE `rest_cti_permissions` SET `name`='ad_off_hour',`displayname`='Admin Off Hour',`description`='Allow to change all incoming call paths' WHERE id = 27;
UPDATE IGNORE `rest_cti_permissions` SET `name`='advanced_off_hour',`displayname`='Advanced Off Hour',`description`='Allow to change user\'s incoming call path and generic inbound routes' WHERE id = 25;
DELETE IGNORE FROM `rest_cti_permissions` WHERE `id`=11 AND `name` = 'QueueMan';
