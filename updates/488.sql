alter table shop_custom_attributes add column sort_order int;
set @order:=0;
update shop_custom_attributes set sort_order=@order:=@order+1 order by name;
	
alter table shop_option_set_options add column sort_order int;
set @order:=0;
update shop_option_set_options set sort_order=@order:=@order+1 order by name;

alter table shop_product_properties add column sort_order int;
set @order:=0;
update shop_product_properties set sort_order=@order:=@order+1 order by id;

alter table shop_property_set_properties add column sort_order int;
set @order:=0;
update shop_property_set_properties set sort_order=@order:=@order+1 order by id;