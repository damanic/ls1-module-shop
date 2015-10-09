CREATE TABLE `shop_customer_groups` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(50) default NULL,
  `name` varchar(100) default NULL,
  `description` text,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_customer_groups(code, name) values ('guest', 'Guest');
insert into shop_customer_groups(code, name) values ('registered', 'Registered');
insert into shop_customer_groups(name) values ('Wholesale');
insert into shop_customer_groups(name) values ('Retail');

alter table shop_customers add column customer_group_id int;
create index customer_group_id on shop_customers(customer_group_id);

update shop_customers 
set customer_group_id=(select id from shop_customer_groups where code='guest')
where guest is not null and guest=1;

update shop_customers 
set customer_group_id=(select id from shop_customer_groups where code='registered')
where guest is null or guest=0;