CREATE TABLE `shop_currency_price_records` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `master_object_class` varchar(255) DEFAULT NULL,
   `master_object_id` int(11) DEFAULT NULL,
   `master_field_name` varchar(255) DEFAULT NULL,
   `currency_id` int(11) DEFAULT NULL,
   `value` decimal(15,2) DEFAULT NULL,
   `deferred_session_key` varchar(255) DEFAULT NULL,
   PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8