ALTER TABLE `shop_payment_transaction_disputes`
DROP COLUMN `payment_method_id`,
ADD COLUMN `shop_payment_transaction_id` INT(11) NULL,
CHANGE `transaction_id` `api_transaction_id` VARCHAR(50)  NULL,
ADD COLUMN `created_user_id` INT(11) NULL AFTER `created_at`,
ADD COLUMN `updated_user_id` INT(11) NULL AFTER `updated_at`;
