CREATE TABLE `shop_option_matrix_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matrix_record_id` int(11) DEFAULT NULL,
  `option_id` int(11) DEFAULT NULL,
  `option_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `matrix_record_id` (`matrix_record_id`),
  KEY `option_id` (`option_id`),
  KEY `option_value` (`option_value`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_option_matrix_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `in_stock` int(11) DEFAULT NULL,
  `base_price` decimal(15,2) DEFAULT NULL,
  `cost` decimal(15,2) DEFAULT NULL,
  `sort_order` int NULL,
  `tier_price_compiled` text,
  `disabled` tinyint(4),
  `expected_availability_date` date DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `width` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `depth` float DEFAULT NULL,
  `price_rules_compiled` text,
  `price_rule_map_compiled` text,
  `on_sale` tinyint(4) DEFAULT NULL,
  `sale_price_or_discount` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `sku` (`sku`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;