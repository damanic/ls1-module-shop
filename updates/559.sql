CREATE TABLE `shop_automated_billing_settings` (
  `id` int(11) NOT NULL auto_increment,
  `payment_method_id` int(11) default NULL,
  `billing_period` int(11) default NULL,
  `success_notification_id` int(11) default NULL,
  `failed_notification_id` int(11) default NULL,
  `enabled` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_automated_billing_settings(id, billing_period, enabled) values (1, 5, 0);

alter table shop_orders add column automated_billing_fail datetime;
alter table shop_orders add column automated_billing_success datetime;

create index automated_billing_fail on shop_orders(automated_billing_fail);
create index automated_billing_success on shop_orders(automated_billing_success);

insert into system_email_templates(code, subject, content, description, is_system) values('shop:auto_billing_report', 'Automated billing report', '<p>Hi!</p><p>LemonStand finished processing the automated billing. Below is a detailed report.</p><p>{autobilling_report}</p>', 'This message is sent to administrators when LemonStand finishes processing the automated billing.', 1);