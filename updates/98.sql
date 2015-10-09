alter table shop_payment_methods add column ls_api_code varchar(50);
create index ls_api_code on shop_payment_methods(ls_api_code);
	
alter table shop_shipping_options add column ls_api_code varchar(50);
create index ls_api_code on shop_shipping_options(ls_api_code);
