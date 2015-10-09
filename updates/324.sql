CREATE TABLE `shop_shippingoptions_customer_groups` (
  `shop_sh_option_id` int(11) NOT NULL default '0',
  `customer_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_sh_option_id`,`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;