USE asterisk;
CREATE TABLE IF NOT EXISTS `rest_users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voicemail_password` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webrtc_password` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE `user_profile` (`user_id`,`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
