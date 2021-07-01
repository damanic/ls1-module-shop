ALTER TABLE `shop_company_information`
	ADD COLUMN `tax_identification_number` VARCHAR(50) NULL;

ALTER TABLE `shop_customer_notifications`
	ADD  INDEX `is_system_index` (`is_system`),
	ADD  INDEX `customer_id_index` (`customer_id`);