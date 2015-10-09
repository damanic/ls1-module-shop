alter table shop_extra_option_sets add column code varchar(50) default NULL;
create index api_code on shop_extra_option_sets(code);