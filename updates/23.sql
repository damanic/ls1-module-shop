CREATE TABLE `shop_customer_cart_items` (
  `id` int(11) NOT NULL auto_increment,
  `customer_id` int(11) default NULL,
  `product_id` int(11) default NULL,
  `options` text,
  `extras` text,
  `quantity` int(11) default NULL,
  `postponed` smallint(6) default NULL,
  `item_key` varchar(32) default NULL,
  PRIMARY KEY  (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8