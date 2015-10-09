CREATE TABLE `shop_paymentmethods_customer_groups` (
  `shop_payment_method_id` int(11) NOT NULL DEFAULT '0',
  `customer_group_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`shop_payment_method_id`,`customer_group_id`),
  KEY `shop_payment_method_id` (`shop_payment_method_id`),
  KEY `customer_group_id` (`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;