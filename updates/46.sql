CREATE TABLE `shop_roles` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `description` text,
  `can_create_orders` tinyint(4) default NULL,
  `notified_on_out_of_stock` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_roles(name, description, can_create_orders, notified_on_out_of_stock) values ('Default', 'Default general role.', 1, 1);