alter table shop_configuration 
add column search_in_short_descriptions tinyint,
add column search_in_long_descriptions tinyint,
add column search_in_categories tinyint,
add column search_in_manufacturers tinyint,
add column search_in_sku tinyint;

update shop_configuration set search_in_short_descriptions=1, search_in_long_descriptions=1, search_in_categories=1;