alter table shop_payment_methods add column backend_enabled tinyint(4) NULL;
update shop_payment_methods set backend_enabled = enabled;

alter table shop_shipping_options add column backend_enabled tinyint(4) NULL;
update shop_shipping_options set backend_enabled = 1;