USE asterisk;
CREATE TABLE IF NOT EXISTS rest_user_passwords (
  username varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  password varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  voicemail_password varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '0000',
  PRIMARY KEY (username)
);
