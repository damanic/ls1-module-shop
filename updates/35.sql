CREATE TABLE `shop_order_items` (
  `id` int(11) NOT NULL auto_increment,
  `shop_product_id` int(11) default NULL,
  `price` decimal(15,2) default NULL,
  `shop_order_id` int(11) default NULL,
  `quantity` int(11) default NULL,
  `options` text default NULL,
  `extras` text default NULL,
  PRIMARY KEY  (`id`),
  KEY `shop_order_id` (`shop_order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;