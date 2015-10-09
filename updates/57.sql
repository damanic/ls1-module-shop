alter table shop_countries add column code_iso_numeric int;

update shop_countries set code_iso_numeric=840 where code='US';
update shop_countries set code_iso_numeric=124 where code='CA';