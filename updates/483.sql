alter table shop_products add column cost decimal(15,2);
alter table shop_order_items add column cost decimal(15,2);
alter table shop_orders add column total_cost decimal(15,2);