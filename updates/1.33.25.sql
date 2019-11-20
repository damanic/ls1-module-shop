ALTER TABLE `shop_catalog_rules`
	CHANGE `date_start` `date_start` DATETIME NULL,
	CHANGE `date_end` `date_end` DATETIME NULL;

ALTER TABLE `shop_cart_rules`
	CHANGE `date_start` `date_start` DATETIME NULL,
	CHANGE `date_end` `date_end` DATETIME NULL;
