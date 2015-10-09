alter table shop_shipping_params add column sender_first_name varchar(255);
alter table shop_shipping_params add column sender_last_name varchar(255);
alter table shop_shipping_params add column street_addr varchar(255);
alter table shop_shipping_params add column sender_company varchar(255);
alter table shop_shipping_params add column sender_phone varchar(100);

CREATE TABLE `shop_order_shipping_track_codes` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) default NULL,
  `shipping_method_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `code` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_order_shipping_label_params` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) default NULL,
  `shipping_method_id` int(11) default NULL,
  `xml_data` text,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `shipping_method_id` (`shipping_method_id`,`order_id`),
  KEY `updated_at` (`updated_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;