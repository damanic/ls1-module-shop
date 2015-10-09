CREATE TABLE `shop_order_notifications` (
  `id` int(11) NOT NULL auto_increment,
  `order_id` int(11) default NULL,
  `email` text,
  `created_at` datetime default NULL,
  `created_user_id` int(11) default NULL,
  `subject` varchar(255) default NULL,
  `message` text,
  `reply_to_email` varchar(100) default NULL,
  `reply_to_name` varchar(100) default NULL,
  `is_system` tinyint(4) default NULL,
  PRIMARY KEY  (`id`),
  KEY `order_id` (`order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_order_notifications(id, order_id, email, created_at, created_user_id, subject, message, reply_to_email, reply_to_name, is_system) select id, order_id, email, created_at, created_user_id, subject, message, reply_to_email, reply_to_name, 0 from shop_customer_order_notifications;
	
drop table shop_customer_order_notifications;