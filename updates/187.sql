alter table shop_orders 
add column shipping_tax_1 decimal(15,2),
add column shipping_tax_2 decimal(15,2),
add column shipping_tax_name_1 varchar(30),
add column shipping_tax_name_2 varchar(30);

update shop_orders set shipping_tax_name_1='TAX', shipping_tax_2=0, shipping_tax_1=shipping_tax;

alter table shop_order_items 
add column tax_2 decimal(15,2),
add column tax_name_1 varchar(30),
add column tax_name_2 varchar(30),
add column tax_discount_1 decimal(15,2),
add column tax_discount_2 decimal(15,2);

update shop_order_items set tax_name_1='TAX', tax_discount_1=0, tax_discount_2=0;