CREATE TABLE `shop_order_status_log_records` (
  `id` int(11) NOT NULL auto_increment,
  `created_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `status_id` int(11) default NULL,
  `comment` text,
  `order_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`),
  KEY `status_id` (`status_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;