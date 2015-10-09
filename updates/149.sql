alter table shop_categories add column code varchar(50);
create index api_code on shop_categories(code);