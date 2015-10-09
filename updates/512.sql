alter table shop_customer_cart_items add column created_at datetime;
create index created_at on shop_customer_cart_items(created_at);