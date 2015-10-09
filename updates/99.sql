alter table shop_products 
	add column grouped_sort_order int,
	add column grouped_self_ghost_flag tinyint;

update shop_products set grouped_sort_order=id;