alter table shop_customer_cart_items add column cart_name varchar(15) default 'main';
create index cart_name on shop_customer_cart_items(cart_name);