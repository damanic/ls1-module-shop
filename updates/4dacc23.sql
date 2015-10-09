alter table shop_order_statuses add column update_stock tinyint(4);
update shop_order_statuses set update_stock=1 where code='paid';