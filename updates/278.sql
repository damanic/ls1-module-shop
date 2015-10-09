alter table shop_products add column disable_completely tinyint;
create index disable_completely on shop_products(disable_completely);