CREATE TABLE `shop_shipping_tracker_providers` (
`id` INT NOT NULL AUTO_INCREMENT,
`name` VARCHAR(45) NULL,
`tracker_url_format` VARCHAR(2048) NULL,
PRIMARY KEY (`id`));

ALTER TABLE `shop_order_shipping_track_codes`
    ADD COLUMN `shop_shipping_tracker_provider_id` INT NULL DEFAULT NULL AFTER `code`;
