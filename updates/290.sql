alter table shop_countries add column enabled_in_backend tinyint;
update shop_countries set enabled_in_backend = enabled;
create index enabled_in_backend on shop_countries(enabled_in_backend);