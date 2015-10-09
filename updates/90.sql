alter table shop_orders
add column discount decimal(15,2) default 0,
add column tax_discount decimal(15,2) default 0,
add column free_shipping tinyint,
add column auto_discount_price_eval tinyint;