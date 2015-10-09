CREATE TABLE `shop_categories` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `description` text,
  `short_description` text,
  `url_name` varchar(100) default NULL,
  `page_id` int(11) default NULL,
  `meta_description` text,
  `meta_keywords` text,
  `category_id` int(11) default NULL,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `url_name` (`url_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;