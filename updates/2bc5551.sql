alter table shop_customers add column password_restore_hash varchar(150) NULL;
alter table shop_customers add column password_restore_time datetime NULL;

insert into system_email_templates (code, subject, content, description, is_system) values ('shop:password_restore', 'Password restore', '<p>Dear {customer_name}!</p>
<p>Please visit our store within 24 hours to set a new password for your account here: {password_restore_page_link}.</p><p>Thank you</p>', 'Message to send to a customer when they request a password restore.', 0);