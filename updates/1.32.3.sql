ALTER TABLE `shop_products`
	ADD COLUMN `shipping_hs_code` VARCHAR(255) NULL;

ALTER TABLE `shop_shipping_params`
  ADD COLUMN `enable_hs_codes` TINYINT(4) NULL;
