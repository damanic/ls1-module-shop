alter table shop_cart_rules add column parameters_serialized text;
alter table shop_catalog_rules add column parameters_serialized text;
alter table shop_price_rule_conditions add column parameters_serialized text;