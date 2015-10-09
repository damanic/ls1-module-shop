CREATE TABLE `shop_custom_group` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `code` varchar(100) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_products_customgroups` (
  `shop_product_id` int(11) NOT NULL default '0',
  `shop_custom_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_product_id`,`shop_custom_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;