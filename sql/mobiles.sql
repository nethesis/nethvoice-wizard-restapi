USE asterisk;
DROP TABLE IF EXISTS `rest_mobiles`;
CREATE TABLE IF NOT EXISTS `rest_mobiles`(
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `mobile` varchar(20), 
  `username` varchar(150) DEFAULT NULL
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


