CREATE TABLE `shop_product_price_index` (
  `pi_product_id` int(11) NOT NULL default '0',
  `pi_group_id` int(11) NOT NULL default '0',
  `price` decimal(15,2) default NULL,
  PRIMARY KEY  (`pi_product_id`,`pi_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;