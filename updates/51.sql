CREATE TABLE `shop_order_deleted_status` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `code` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_order_deleted_status(name, code) values ('Active', 'active');
insert into shop_order_deleted_status(name, code) values ('Deleted', 'deleted');