CREATE TABLE `shop_currency_exchange_rates` (
  `id` int(11) NOT NULL auto_increment,
  `created_at` datetime default NULL,
  `from_currency` varchar(3) default NULL,
  `to_currency` varchar(3) default NULL,
  `rate` decimal(15,4) default NULL,
  PRIMARY KEY  (`id`),
  KEY `from_currency_to_currency` (`from_currency`,`to_currency`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;