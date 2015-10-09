alter table shop_manufacturers add column url_name varchar(100);
update shop_manufacturers set url_name=id;
create index url_name on shop_manufacturers(url_name);