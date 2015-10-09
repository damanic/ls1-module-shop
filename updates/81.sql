CREATE TABLE `shop_catalog_rules` (
  `id` int(11) NOT NULL auto_increment,
  `date_start` date default NULL,
  `date_end` date default NULL,
  `name` varchar(255) default NULL,
  `description` text,
  `active` tinyint(4) default NULL,
  `sort_order` int(11) default NULL,
  `terminating` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_catalog_rules_customer_groups` (
  `shop_catalog_rule_id` int(11) NOT NULL default '0',
  `shop_customer_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`shop_catalog_rule_id`,`shop_customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_price_rule_conditions` (
  `id` int(11) NOT NULL auto_increment,
  `rule_parent_id` int(11) default NULL,
  `class_name` varchar(100) default NULL,
  `xml_data` text,
  `rule_host_id` int(11) default NULL,
  `host_rule_set` varchar(100) default NULL,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `host_id, host_rule_set` (`rule_host_id`,`host_rule_set`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;