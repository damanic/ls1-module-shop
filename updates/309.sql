CREATE TABLE `shop_product_ratings` (
  `id` int(11) NOT NULL auto_increment,
  `prt_product_id` int(11) default NULL,
  `value` smallint(6) default NULL,
  `created_at` datetime default NULL,
  `created_customer_id` int(11) default NULL,
  `customer_ip` varchar(15) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `prt_product_id` (`prt_product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `shop_product_reviews` (
  `id` int(11) NOT NULL auto_increment,
  `prv_product_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `created_customer_id` int(11) default NULL,
  `customer_ip` varchar(15) default NULL,
  `prv_rating` int(11) default NULL,
  `review_text` text,
  `review_title` varchar(255) default NULL,
  `prv_moderation_status` char(20) default NULL,
  `review_author_name` varchar(255) default NULL,
  `review_author_email` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `prv_product_id` (`prv_product_id`),
  KEY `moderation_status` (`prv_moderation_status`),
  KEY `customer_ip` (`customer_ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;