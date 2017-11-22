ALTER TABLE `shop_payment_transactions`
  ADD  INDEX `created_at` (`created_at`),
  ADD  INDEX `transaction_value` (`transaction_value`),
  ADD  INDEX `transaction_complete` (`transaction_complete`),
  ADD  INDEX `transaction_refund` (`transaction_refund`),
  ADD  INDEX `transaction_void` (`transaction_void`),
  ADD  INDEX `payment_method_id` (`payment_method_id`);
