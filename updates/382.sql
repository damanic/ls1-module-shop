alter table shop_orders add column parent_order_id int;
create index parent_order_id on shop_orders(parent_order_id);