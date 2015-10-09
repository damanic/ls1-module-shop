alter table shop_extra_options add column extra_option_sort_order int;

update shop_extra_options set extra_option_sort_order=id;