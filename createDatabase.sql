create database crawlerData;
use crawlerData;
grant usage on *.* to crawler@localhost identified by 'crawl123';
grant all privileges on crawlerData.* to crawler@localhost;




CREATE TABLE IF NOT EXISTS `keywords` (
`keyword_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
`keyword` VARCHAR(255) NOT NULL DEFAULT '',
PRIMARY KEY (`keyword_id`),
UNIQUE (keyword)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

CREATE TABLE IF NOT EXISTS `URLs` (
`URL_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
`URL` VARCHAR(255) NOT NULL DEFAULT '',
PRIMARY KEY (`URL_id`),
UNIQUE (URL)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

CREATE TABLE IF NOT EXISTS `keywords_URLs` (
`keyword_id` INT UNSIGNED NOT NULL,
`URL_id` INT UNSIGNED NOT NULL,
FOREIGN KEY (`keyword_id`)
REFERENCES `keywords`(`keyword_id`)
ON DELETE CASCADE,
FOREIGN KEY (`URL_id`)
REFERENCES `URLs`(`URL_id`)
ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;



