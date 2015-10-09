CREATE TABLE `shop_order_payment_log` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `is_successful` tinyint(4) default NULL,
  `message` varchar(255) default NULL,
  `request_data` text,
  `response_data` text,
  `raw_response_text` text,
  `order_id` int(11) default NULL,
  `payment_method_name` varchar(255) default NULL,
  `created_user_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `created_at` (`created_at`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;