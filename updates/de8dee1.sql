UPDATE `shop_products` SET `on_sale` = null;
ALTER TABLE `shop_products` ADD `sale_price_or_discount` VARCHAR( 50 ) NULL;