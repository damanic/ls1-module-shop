alter table shop_products add column xml_data text;

alter table shop_product_types
add column xml tinyint default 0;
