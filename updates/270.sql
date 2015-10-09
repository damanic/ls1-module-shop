CREATE TABLE `shop_products_customer_groups` (
  `shop_product_id` int(11) NOT NULL default '0',
  `customer_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_product_id`,`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

alter table shop_products add column enable_customer_group_filter tinyint;