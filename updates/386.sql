alter table shop_product_properties drop index value;
alter table shop_product_properties change column `value` `value` varchar(500);
alter table shop_property_set_properties change column `value` `value` varchar(500);