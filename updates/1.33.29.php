<?php

$dependant_field_exists_sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'shop_order_applied_rules' AND COLUMN_NAME = 'shop_cart_rule_serialized'";
$update_dependant_sql = "ALTER TABLE `shop_order_applied_rules` ADD COLUMN `shop_cart_rule_serialized` TEXT NULL;";

if(!Db_DbHelper::scalar($dependant_field_exists_sql)){
	Db_DbHelper::query($update_dependant_sql);
}
