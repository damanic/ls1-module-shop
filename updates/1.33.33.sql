ALTER TABLE `shop_order_statuses`
ADD COLUMN `requires_payment_transaction_refunds` TINYINT(4) NULL AFTER `order_lock_action`;
