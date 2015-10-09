alter table shop_product_price_index add column min_price decimal(15,2);
alter table shop_product_price_index add column max_price decimal(15,2);
create index product_price on shop_product_price_index(price);
create index max_price on shop_product_price_index(max_price);
create index min_price on shop_product_price_index(min_price);