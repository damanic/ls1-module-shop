ALTER TABLE `shop_orders`
ADD COLUMN `shop_currency_code` VARCHAR(4) NULL AFTER `shop_currency_rate`;
