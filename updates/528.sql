CREATE TABLE `shop_customer_payment_profiles` (
  `id` int(11) NOT NULL auto_increment,
  `customer_id` int(11) default NULL,
  `payment_method_id` int(11) default NULL,
  `profile_data` text,
  `cc_four_digits_num` text,
  PRIMARY KEY  (`id`),
  KEY `customer_id` (`customer_id`,`payment_method_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;