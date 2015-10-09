CREATE TABLE `shop_cart_rules` (
  `id` int(11) NOT NULL auto_increment,
  `date_start` date default NULL,
  `date_end` date default NULL,
  `name` varchar(255) default NULL,
  `description` text,
  `active` tinyint(4) default NULL,
  `sort_order` int(11) default NULL,
  `terminating` tinyint(4) default NULL,
  `action_class_name` varchar(100) default NULL,
  `action_xml_data` text,
  `coupon_id` int(11) default NULL,
  `max_coupon_uses` int(11) default NULL,
  `max_customer_uses` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_cart_rules_customer_groups` (
  `shop_cart_rule_id` int(11) NOT NULL default '0',
  `shop_customer_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_cart_rule_id`,`shop_customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_coupons` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;