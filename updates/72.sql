CREATE TABLE `shop_currency_converter_params` (
  `id` int(11) NOT NULL auto_increment,
  `class_name` varchar(100) default NULL,
  `refresh_interval` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

insert into shop_currency_converter_params(class_name, refresh_interval) values ('Shop_Ecb_Converter', 24);