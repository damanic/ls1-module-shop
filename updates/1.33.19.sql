CREATE TABLE shop_customer_notifications LIKE shop_order_notifications;
INSERT shop_customer_notifications SELECT * FROM shop_order_notifications;

ALTER TABLE `shop_customer_notifications`
ADD COLUMN `customer_id` INT(11) NULL AFTER `id`;

UPDATE shop_customer_notifications
SET shop_customer_notifications.customer_id = (
	SELECT shop_orders.customer_id
	FROM shop_orders
	WHERE shop_orders.id = shop_customer_notifications.order_id
);
