CREATE TABLE `shop_custom_attributes` (
  `id` int(11) NOT NULL auto_increment,
  `product_id` int(11) default NULL,
  `name` varchar(255) default NULL,
  `attribute_values` text,
  PRIMARY KEY  (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;