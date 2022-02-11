ALTER TABLE `shop_orders` ADD INDEX (`order_datetime`);

ALTER TABLE `shop_customer_cart_items`
	CHANGE `bundle_master_item_id` `bundle_offer_id` INT(11) NULL,
	CHANGE `bundle_master_item_product_id` `bundle_offer_item_id` INT(11) NULL;

ALTER TABLE `shop_order_items`
	CHANGE `bundle_master_bundle_item_id` `bundle_offer_id` INT(11) NULL,
	CHANGE `bundle_master_bundle_item_name` `bundle_offer_name` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL;
