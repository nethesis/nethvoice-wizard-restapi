USE asterisk;
CREATE TABLE IF NOT EXISTS `rest_user_passwords` (
  `username` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `voicemail_password` varchar(5) NOT NULL DEFAULT '0000',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;;
