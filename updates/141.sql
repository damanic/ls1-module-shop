alter table shop_tax_classes add column is_default tinyint;
update shop_tax_classes set is_default=1 where name='Product';