CREATE TABLE `shop_configuration` (
  `id` int(11) NOT NULL auto_increment,
  `cart_login_behavior` varchar(20) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_configuration(cart_login_behavior) values('move_and_sum');