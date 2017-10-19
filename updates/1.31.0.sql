ALTER TABLE `shop_payment_transactions`
  ADD COLUMN `transaction_value` DECIMAL(15,2) NULL,
  ADD COLUMN `transaction_complete` TINYINT(4) NULL,
  ADD COLUMN `transaction_refund` TINYINT(4) NULL,
  ADD COLUMN `transaction_void` TINYINT(4) NULL;
