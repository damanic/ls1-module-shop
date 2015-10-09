alter table shop_extra_options add column group_name varchar(255);
create index group_name on shop_extra_options(group_name);