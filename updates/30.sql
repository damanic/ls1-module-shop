CREATE TABLE `shop_payment_methods` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  `class_name` varchar(100) default NULL,
  `enabled` tinyint(4) default NULL,
  `config_data` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_paymentmethods_countries` (
  `shop_payment_method_id` int(11) default NULL,
  `shop_country_id` int(11) default NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
