CREATE TABLE `shop_order_lock_logs` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`order_id` int(11) DEFAULT NULL,
	`status_id` int(11) DEFAULT NULL,
	`locked_state` tinyint(4) DEFAULT NULL,
	`comment` varchar(255) DEFAULT NULL,
	`created_at` datetime DEFAULT NULL,
	`created_user_id` int(11) DEFAULT NULL,
	`updated_at` datetime DEFAULT NULL,
	`updated_user_id` int(11) DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `order_id` (`order_id`),
	KEY `status_id` (`status_id`)
);