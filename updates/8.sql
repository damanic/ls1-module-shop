CREATE TABLE `shop_related_products` (
  `master_product_id` int(11) NOT NULL default '0',
  `related_product_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`master_product_id`,`related_product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;