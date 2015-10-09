alter table shop_products add column perproduct_shipping_cost_use_parent tinyint(4);
update shop_products set perproduct_shipping_cost_use_parent=1;