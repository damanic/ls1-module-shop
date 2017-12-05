ALTER TABLE `shop_orders`
  ADD COLUMN `locked` TINYINT(4) NULL;

ALTER TABLE `shop_order_statuses`
  ADD COLUMN `order_lock_action` TINYINT(4);