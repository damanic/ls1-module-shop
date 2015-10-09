alter table shop_orders add column coupon_id int;
create index coupon_id on shop_orders(coupon_id);
	
CREATE TABLE `shop_order_applied_rules` (
  `shop_order_id` int(11) NOT NULL default '0',
  `shop_cart_rule_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_order_id`,`shop_cart_rule_id`),
  KEY `shop_cart_rule_id` (`shop_cart_rule_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;