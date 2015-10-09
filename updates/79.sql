CREATE TABLE `shop_tier_prices` (
  `id` int(11) NOT NULL auto_increment,
  `product_id` int(11) default NULL,
  `customer_group_id` int(11) default NULL,
  `quantity` int(11) default NULL,
  `price` decimal(15,2) default NULL,
  PRIMARY KEY  (`id`),
  KEY `product_id` (`product_id`),
  KEY `customer_group_id` (`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE shop_products
ADD COLUMN tier_prices_per_customer tinyint;