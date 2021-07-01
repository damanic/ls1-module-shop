ALTER TABLE `shop_customer_notifications`
	ADD  INDEX `is_system_index` (`is_system`),
	ADD  INDEX `customer_id_index` (`customer_id`);