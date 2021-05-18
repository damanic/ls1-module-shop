ALTER TABLE `shop_states`
	ADD COLUMN `disabled` TINYINT(4),
	ADD INDEX (`disabled`);
