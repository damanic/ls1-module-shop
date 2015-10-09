CREATE TABLE `shop_status_notifications` (
  `id` int(11) NOT NULL auto_increment,
  `shop_status_id` int(11) default NULL,
  `shop_role_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `shop_status_id` (`shop_status_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;