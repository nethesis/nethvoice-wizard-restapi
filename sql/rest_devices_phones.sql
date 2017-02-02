USE asterisk;
CREATE TABLE IF NOT EXISTS `rest_devices_phones`(
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `mac` varchar(20),
  `vendor` varchar(64) DEFAULT NULL,
  `model` varchar(64) DEFAULT NULL,
  `line` int DEFAULT '1',
  `mainextension` varchar(16) DEFAULT NULL,
  `extension` varchar(16) DEFAULT NULL,
  `secret` varchar(128) DEFAULT NULL,
  UNIQUE KEY `mac` (`mac`,`line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
