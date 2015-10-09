CREATE TABLE `shop_order_notes` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `note` text,
  `order_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;