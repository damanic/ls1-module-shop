CREATE TABLE `shop_shipping_delivery_estimate` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`shipping_zone_id` int(11) DEFAULT NULL,
	`shipping_service_level_id` int(11) DEFAULT NULL,
	`min_days` int(3) DEFAULT NULL,
	`max_days` int(3) DEFAULT NULL,
	`as_text` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `shipping_zone_id` (`shipping_zone_id`),
	KEY `shipping_service_level_id` (`shipping_service_level_id`)
);

CREATE TABLE `shop_shipping_service_level` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`params_id` int(11) DEFAULT NULL,
	`name` varchar(255) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `params_id` (`params_id`)
);