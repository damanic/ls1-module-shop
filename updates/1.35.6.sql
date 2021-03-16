CREATE TABLE `shop_order_lock_logs`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`order_id` INT(11),
	`status_id` INT(11),
	`locked_state` TINYINT,
	`comment` VARCHAR(255),
	PRIMARY KEY (`id`),
	INDEX (`order_id`),
	INDEX (`status_id`)
);
