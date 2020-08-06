CREATE TABLE `shop_payment_transaction_disputes`
(
	`id`                 INT(11) NOT NULL AUTO_INCREMENT,
	`payment_method_id`  INT(11),
	`transaction_id`     INT(11),
	`case_id`            VARCHAR(255),
	`amount_disputed`    DECIMAL(15, 2),
	`amount_lost`        DECIMAL(15, 2),
	`status_description` VARCHAR(255),
	`reason_description` VARCHAR(255),
	`case_closed`        TINYINT(4),
	`notes`              TEXT,
	`gateway_api_data`   TEXT,
	`created_at`         DATETIME,
	`updated_at`         DATETIME,
	PRIMARY KEY (`id`)
);

ALTER TABLE `shop_payment_transactions`
	ADD COLUMN `has_disputes` TINYINT(4) NULL,
	ADD COLUMN `liability_shifted` TINYINT(4) NULL;

ALTER TABLE `shop_tax_classes`
	ADD  INDEX `code` (`code`);
