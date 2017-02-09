USE asterisk;

CREATE TABLE IF NOT EXISTS `rest_cti_profiles`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL DEFAULT 'Custom'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_macro_permissions`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL DEFAULT ''
  `description` varchar(1024) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cti_permissions`(
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(1024) NOT NULL DEFAULT ''
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

