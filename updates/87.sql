alter table shop_order_items 
add column discount decimal(15, 2),
add column auto_discount_price_eval tinyint;