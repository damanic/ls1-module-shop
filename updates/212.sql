CREATE TABLE `shop_customer_order_notifications` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) default NULL,
  `email` varchar(150) default NULL,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `subject` varchar(255) default NULL,
  `message` text,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;