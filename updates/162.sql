CREATE TABLE `shop_manufacturers` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  `country_id` int(11) default NULL,
  `state_id` int(11) default NULL,
  `address` varchar(255) default NULL,
  `zip` varchar(20) default NULL,
  `phone` varchar(50) default NULL,
  `fax` varchar(50) default NULL,
  `city` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `url` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `country_id` (`country_id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE INDEX manufacturer_id ON shop_products(manufacturer_id);