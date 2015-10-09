CREATE TABLE `shop_extra_options` (
  `id` int(11) NOT NULL auto_increment,
  `description` text,
  `price` decimal(15,2) default NULL,
  `product_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;