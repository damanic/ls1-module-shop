alter table shop_custom_attributes change column attribute_values attribute_values varchar(255);
create index attribute_values on shop_custom_attributes(attribute_values);
create index name on shop_custom_attributes(name);
	
alter table shop_product_properties change column value value varchar(255);
create index name on shop_product_properties(name);
create index value on shop_product_properties(value);