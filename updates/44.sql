CREATE TABLE `shop_product_types` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `shipping` tinyint,
  `files` tinyint,
  `inventory` tinyint,
  `is_default` tinyint,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

alter table shop_products add column product_type_id int;

insert into shop_product_types(id, name, shipping, files, inventory, is_default) values (1, 'Goods', 1, 0, 1, 1);
insert into shop_product_types(id, name, shipping, files, inventory) values (2, 'Downloadable', 0, 1, 0);
insert into shop_product_types(id, name, shipping, files, inventory) values (3, 'Service', 0, 0, 0);
	
update shop_products set product_type_id=1;