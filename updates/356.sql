CREATE TABLE `shop_extra_option_sets` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_products_extra_sets` (
  `extra_product_id` int(11) NOT NULL default '0',
  `extra_option_set_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`extra_product_id`,`extra_option_set_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE shop_extra_options add column option_in_set tinyint;