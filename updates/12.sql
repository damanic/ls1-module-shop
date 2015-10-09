CREATE TABLE `shop_currency_settings` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(10) default NULL,
  `dec_point` varchar(1) default NULL,
  `thousands_sep` varchar(1) default NULL,
  `sign` varchar(10) default NULL,
  `sign_before` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;