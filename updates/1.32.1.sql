CREATE TABLE `shop_customer_preferences`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`customer_id` INT(11),
	`pref_hash` VARCHAR(32),
	`pref_field` VARCHAR(255),
	`pref_value` TEXT,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `customer_field` (`customer_id`, `pref_field`),
	UNIQUE INDEX `pref_hash` (`pref_hash`)
);
