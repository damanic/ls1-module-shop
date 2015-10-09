create index product_category on shop_products_categories(shop_product_id, shop_category_id);
create index manufacturer on shop_products(manufacturer_id);
create index page_id on shop_products(page_id);
create index product_type_id on shop_products(product_type_id);
create index tax_class_id on shop_products(tax_class_id);
create index product_id on shop_products(product_id);
create index grouped on shop_products(grouped);