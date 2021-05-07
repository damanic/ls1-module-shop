ALTER TABLE `shop_property_sets`
	ADD COLUMN `api_code` VARCHAR(50);

ALTER TABLE `shop_property_set_properties`
	ADD COLUMN `required` TINYINT(4),
	ADD COLUMN `api_code` VARCHAR(50),
	ADD COLUMN `comment` VARCHAR(255),
  	ADD COLUMN `validate` VARCHAR(10),
  	ADD COLUMN `select_values` TEXT;

CREATE TABLE `shop_property_sets_applied_categories` (
	 `shop_category_id` int(11) DEFAULT NULL,
	 `shop_property_set_id` int(11) DEFAULT NULL,
	 KEY `set_category` (`shop_category_id`,`shop_property_set_id`)
);

ALTER TABLE `shop_product_properties`
    ADD COLUMN `property_set_property_id` INT(11),
	ADD COLUMN `api_code` VARCHAR(50);
