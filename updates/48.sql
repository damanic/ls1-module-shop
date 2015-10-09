alter table shop_order_statuses add column notify_recipient tinyint;
alter table shop_order_statuses add column customer_message_template_id int;

insert into shop_status_transitions(from_state_id, to_state_id, role_id) values ((select id from shop_order_statuses where code='new'), (select id from shop_order_statuses where code='paid'), 1);