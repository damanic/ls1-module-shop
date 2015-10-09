CREATE TABLE `shop_shipping_params` (
  `id` int(11) NOT NULL auto_increment,
  `country_id` int(11) default NULL,
  `state_id` int(11) default NULL,
  `zip_code` varchar(30) default NULL,
  `city` varchar(255) default NULL,
  `weight_unit` varchar(10) default NULL,
  `dimension_unit` varchar(10) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

insert into shop_shipping_params(zip_code) values ('');