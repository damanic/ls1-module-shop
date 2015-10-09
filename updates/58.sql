alter table shop_product_types
add column grouped tinyint default 1,
add column options tinyint default 1,
add column extras tinyint default 1,
add column code varchar(50);

create index code on shop_product_types(code);