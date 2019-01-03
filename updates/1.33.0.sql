CREATE TABLE `shop_shipping_zones` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`params_id` int(11) DEFAULT NULL,
	`name` varchar(255) DEFAULT NULL,
	`delivery_min_days` int(3) DEFAULT NULL,
	`delivery_max_days` int(3) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `params_id` (`params_id`)
);

ALTER TABLE `shop_countries`
	ADD COLUMN `shipping_zone_id` INT(11) NULL,
	ADD  INDEX `shipping_zone_id` (`shipping_zone_id`);
