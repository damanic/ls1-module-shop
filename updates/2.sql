CREATE TABLE `shop_products` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  `short_description` text,
  `url_name` varchar(100) default NULL,
  `price` decimal(15,2) default NULL,
  `sku` varchar(100) default NULL,
  `weight` int(11) default NULL,
  `width` int(11) default NULL,
  `height` int(11) default NULL,
  `depth` int(11) default NULL,
  `manufacturer_id` int(11) default NULL,
  `meta_description` text,
  `meta_keywords` text,
  `on_sale` tinyint(4) default NULL,
  `enabled` tinyint(4) default NULL,
  `track_inventory` tinyint(4) default NULL,
  `in_stock` int(11) default NULL,
  `hide_if_out_of_stock` tinyint(4) default NULL,
  `stock_alert_threshold` int(11) default NULL,
  `allow_pre_order` tinyint(4) default NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  `page_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `url_name` (`url_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_products_categories` (
  `shop_product_id` int(11) default NULL,
  `shop_category_id` int(11) default NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;