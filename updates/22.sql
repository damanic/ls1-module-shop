CREATE TABLE `shop_shipping_options` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  `class_name` varchar(100) default NULL,
  `enabled` tinyint(4) default NULL,
  `config_data` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;