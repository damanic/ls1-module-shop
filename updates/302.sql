alter table shop_products 
	add column enable_perproduct_shipping_cost tinyint,
	add column perproduct_shipping_cost text;