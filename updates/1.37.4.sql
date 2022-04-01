ALTER TABLE `shop_payment_transactions`
    ADD COLUMN `settlement_value` DECIMAL(17,8) NULL,
    ADD COLUMN `settlement_value_currency_code` VARCHAR(4) NULL,
    CHANGE `transaction_value` `transaction_value` DECIMAL(17,8) NULL,
    ADD COLUMN `transaction_value_currency_code` VARCHAR(4) NULL AFTER `transaction_value`;