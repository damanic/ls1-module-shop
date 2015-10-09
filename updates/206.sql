alter table shop_shipping_params
	add column default_shipping_country_id int,
	add column default_shipping_state_id int,
	add column default_shipping_city varchar(100),
	add column default_shipping_zip varchar(30);