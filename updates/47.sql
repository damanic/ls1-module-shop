CREATE TABLE `shop_status_transitions` (
  `id` int(11) NOT NULL auto_increment,
  `from_state_id` int(11) default NULL,
  `to_state_id` int(11) default NULL,
  `role_id` int(11) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

alter table shop_states add column notify_recipients tinyint;