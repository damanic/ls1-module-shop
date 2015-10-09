alter table shop_extra_options add column option_key varchar(35);
create index option_key on shop_extra_options(option_key);
	
alter table shop_custom_attributes add column option_key varchar(35);
create index option_key on shop_custom_attributes(option_key);