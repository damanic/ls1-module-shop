CREATE TABLE `shop_countries` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(2) default NULL,
  `name` varchar(100) default NULL,
  `enabled` tinyint default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_states` (
  `id` int(11) NOT NULL auto_increment,
  `country_id` int(11) default NULL,
  `code` varchar(50) default NULL,
  `name` varchar(100) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;