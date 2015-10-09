CREATE TABLE `shop_payment_transactions` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) default NULL,
  `transaction_id` varchar(100) default NULL,
  `transaction_status_name` varchar(255) default NULL,
  `transaction_status_code` varchar(20) default NULL,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `payment_method_id` int(11) default NULL,
  `user_note` text,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;