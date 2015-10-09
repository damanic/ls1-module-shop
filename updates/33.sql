CREATE TABLE `shop_order_statuses` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(30) default NULL,
  `name` varchar(255) default NULL,
  `color` varchar(30) default NULL,
  `notify_customer` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;