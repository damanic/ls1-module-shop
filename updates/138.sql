alter table shop_categories add column front_end_sort_order int;
update shop_categories set front_end_sort_order=id;