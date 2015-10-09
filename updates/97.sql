CREATE TABLE `shop_option_sets` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_option_set_options` (
  `id` int(11) NOT NULL auto_increment,
  `option_set_id` int(11) default NULL,
  `name` varchar(255) default NULL,
  `attribute_values` text,
  `option_key` varchar(35) default NULL,
  PRIMARY KEY  (`id`),
  KEY `option_set_id` (`option_set_id`),
  KEY `option_key` (`option_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;