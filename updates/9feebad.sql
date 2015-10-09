alter table shop_orders add column order_date date;
update shop_orders set order_date = date(order_datetime);

create index deleted_at_index on shop_orders(deleted_at);
create index status_id_index on shop_orders(status_id);
create index order_date_index on shop_orders(order_date);