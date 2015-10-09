alter table shop_orders add column deleted_at datetime default null;
alter table shop_customers add column deleted_at datetime default null;