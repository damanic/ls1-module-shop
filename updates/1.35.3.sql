CREATE TABLE `shop_customer_email_trace`(
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11),
  `email_hash` VARCHAR(255),
  `created_at` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `customer_email` (`customer_id`, `email_hash`)
);

ALTER TABLE `shop_configuration`
	ADD COLUMN `default_low_stock_threshold` INT(11) NULL,
	ADD COLUMN `low_stock_alert_notification_template_id` INT(11) NULL,
	ADD COLUMN `default_out_of_stock_threshold` INT(11) NULL,
	ADD COLUMN `out_of_stock_alert_notification_template_id` INT(11) NULL;

UPDATE `shop_configuration`
	SET low_stock_alert_notification_template_id = (SELECT id FROM system_email_templates WHERE code = 'shop:low_stock_internal' ),
		out_of_stock_alert_notification_template_id = (SELECT id FROM system_email_templates WHERE code = 'shop:out_of_stock_internal' );
