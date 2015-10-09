alter table shop_countries add column code_3 varchar(3);

update shop_countries set code_3='USA' where code='US';
update shop_countries set code_3='CAN' where code='CA';