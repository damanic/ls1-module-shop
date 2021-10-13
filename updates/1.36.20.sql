ALTER TABLE `shop_orders`
	CHANGE `shipping_tax_1` `shipping_tax_1` DECIMAL(15,6) NULL,
	CHANGE `shipping_tax_2` `shipping_tax_2` DECIMAL(15,6) NULL;

ALTER TABLE `shop_order_items`
	ADD COLUMN `tax_class_id` INT(11) NULL ;
