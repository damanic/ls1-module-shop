alter table shop_products add column total_in_stock int(11);
update shop_products set total_in_stock=in_stock;