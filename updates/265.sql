alter table shop_configuration 
	add column tax_inclusive_label varchar(100),
	add column tax_inclusive_country_id int,
	add column tax_inclusive_state_id int;