USE asterisk;

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

/*Default profiles*/
INSERT IGNORE INTO `rest_cti_profiles` VALUES (1,'Base');
INSERT IGNORE INTO `rest_cti_profiles` VALUES (2,'Standard');
INSERT IGNORE INTO `rest_cti_profiles` VALUES (3,'Advanced');

/*Macro permissions*/
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (1,'settings','Settings','General and notifications settings');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (2,'phonebook','Phonebook','View Phonebook, add contacts, modify and delete own contacts');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (3,'cdr','CDR','View own call history');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (4,'customerd_card','Customerd Card','Allow to view Customer Cards');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (5,'presence_panel','Presence Panel','Allow to view Presence Panel');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (6,'queue_agent','Use queue agent panel','View Queues and queues info, login/logout from queues, enable or disable pause state');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (7,'streaming','Streaming','Allow to view Streaming Panel');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (8,'off_hour','Off Hour','Allow to change of his incoming call paths');
INSERT IGNORE INTO `rest_cti_macro_permissions` VALUES (9,'remote_sites','Remote Sites','Allow to view Remote Sites information');

/*Permissions*/
INSERT IGNORE INTO `rest_cti_permissions` VALUES (1,'call_waiting','Call Waiting','Configure call waiting');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (2,'dnd','DND','Configure do Not Disturb');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (3,'call_forward','Call Forward','Configure Call Forward');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (4,'recording','Recording','Record own conversations. View/listen/delete own recording');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (5,'conference','Conference','Make a conference call');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (6,'parkings','Parkings','View parkings state and pickup parked calls');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (7,'sms','SMS','Send SMS and view own sent SMS history');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (8,'chat','Chat','Use chat service');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (9,'privacy','Privacy','Obfuscate called and caller numbers for other users');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (10,'oppanel','Operation Panel','Enable Operation Panel access');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (11,'queueman','QueueMan','Enable QueueMan access');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (12,'ad_phonebook','Advanced Phonebook','Modify and delete all contacts');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (13,'ad_cdr','PBX CDR','View all users call history');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (14,'ad_sms','Advanced SMS','View every user SMSs');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (15,'spy','Spy','Hear other extensions calls');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (16,'intrude','Intrude','Intrude in calls');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (17,'ad_recording','Advanced Recording','Record anyone call');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (18,'pickup','Pickup','Pick-up any call');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (19,'transfer','Transfer','Transfer everyone call');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (20,'ad_parking','Advanced Parking','Allow to park any call and to pickup them using any extension');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (21,'hangup','Hangup','Hangup everyone call');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (22,'trunks','PBX lines','View PBX lines');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (23,'ad_queue_agent','Advanced queue agent panel','View more queue information and manage every agent queue state');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (24,'lost_queue_call','Lost Queue Calls','Allow to view Queue Recall panel');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (25,'ad_off_hour','Advanced Off Hour','Allow to change of all incoming call paths');
INSERT IGNORE INTO `rest_cti_permissions` VALUES (26,'ad_phone','Advanced Phone','Use phone features (hangup, call, answer) on conversations not owned by the user');

/*Permission inside macro permissions*/
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,1);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,2);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,3);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,4);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,5);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,6);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,7);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,8);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,9);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,10);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (1,11);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (2,12);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (3,13);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (3,14);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,15);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,16);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,17);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,18);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,19);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,20);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,21);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,22);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (5,26);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (6,23);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (6,24);
INSERT IGNORE INTO `rest_cti_macro_permissions_permissions` VALUES (8,25);

/*Permissions enabled by default for each profile*/
/*Base*/
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (1,1);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (1,2);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (1,9);
/*Standard*/
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,1);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,2);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,3);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,4);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,5);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,7);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,8);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,9);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,10);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (2,23);
/*Advanced*/
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,1);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,2);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,3);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,4);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,5);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,6);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,7);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,8);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,10);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,11);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,12);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,13);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,14);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,22);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,23);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,24);
INSERT IGNORE INTO `rest_cti_profiles_permissions` VALUES (3,25);

/*Macro permissions enabled by default for each profile*/
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,1);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,2);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,3);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,4);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,5);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (1,6);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,1);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,2);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,3);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,4);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,5);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,6);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (2,8);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,1);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,2);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,3);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,4);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,5);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,6);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,7);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,8);
INSERT IGNORE INTO `rest_cti_profiles_macro_permissions` VALUES (3,9);

