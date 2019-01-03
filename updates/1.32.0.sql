ALTER TABLE `shop_orders`
	ADD COLUMN `shipping_discount` DECIMAL(15,2) DEFAULT 0.00  NULL AFTER `free_shipping`,
  ADD COLUMN `manual_shipping_quote` DECIMAL(15,2) DEFAULT 0.00  NULL AFTER `shipping_discount`;
