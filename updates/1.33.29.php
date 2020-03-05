<?php
try {
	$dependant_field_exists_sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'shop_order_applied_rules' AND COLUMN_NAME = 'shop_cart_rule_serialized'";
	$update_dependant_sql       = "ALTER TABLE `shop_order_applied_rules` ADD COLUMN `shop_cart_rule_serialized` TEXT NULL;";

//In case of multi installs, query correct schema
	$schema = Db_DbHelper::scalar( 'SELECT DATABASE()' );
	if ( $schema ) {
		$dependant_field_exists_sql .= ' AND TABLE_SCHEMA = ?';
	}

	if ( !Db_DbHelper::scalar( $dependant_field_exists_sql, $schema ) ) {
		Db_DbHelper::query( $update_dependant_sql );
	}
} catch (Exception $e){
	traceLog('Shop Module update failed (1.33.29). Check that that the TXT column `shop_cart_rule_serialized` exists in TABLE `shop_order_applied_rules`');
}
