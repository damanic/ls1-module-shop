CREATE TABLE `shop_product_bundle_items` (
  `id` int(11) NOT NULL auto_increment,
  `product_id` int(11) default NULL,
  `sort_order` int(11) default NULL,
  `is_required` tinyint(4) default NULL,
  `control_type` char(20) default NULL,
  `name` varchar(255) default NULL,
  `description` text default NULL,
  PRIMARY KEY  (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_bundle_item_products` (
  `id` int(11) NOT NULL auto_increment,
  `item_id` int(11) default NULL,
  `product_id` int(11) default NULL,
  `sort_order` int(11) default NULL,
  `default_quantity` int(11) default NULL,
  `allow_manual_quantity` tinyint(4) default NULL,
  `is_default` tinyint(4) default NULL,
  `price_override_mode` varchar(20) default NULL,
  `price_or_discount` decimal(15,2) default NULL,
  `is_active` tinyint(4) default NULL,
  PRIMARY KEY  (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE shop_order_items
  add column bundle_master_order_item_id int,
  add column bundle_master_bundle_item_id int,
  add column bundle_master_bundle_item_name varchar(255)
;

ALTER TABLE shop_customer_cart_items
  add column bundle_master_cart_key varchar(32),
  add column bundle_master_item_id int,
  add column bundle_master_item_product_id int
;

ALTER TABLE shop_products 
  add column visibility_search tinyint(4) default 1,
  add column visibility_catalog tinyint(4) default 1;

CREATE INDEX visibility_search on shop_products(visibility_search);
CREATE INDEX visibility_catalog on shop_products(visibility_catalog);