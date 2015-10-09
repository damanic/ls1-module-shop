alter table shop_orders add column status_id int;
alter table shop_orders add column order_hash varchar(40);
alter table shop_orders add column total numeric (15,2);
alter table shop_orders add column subtotal numeric (15,2);
alter table shop_orders add column status_update_datetime datetime;

create index order_hash on shop_orders(order_hash);
create index shipping_method_id on shop_orders(shipping_method_id);
create index payment_method_id on shop_orders(payment_method_id);

insert into shop_order_statuses(code, name, color) values ('new', 'New', '#0099cc');
insert into shop_order_statuses(code, name, color) values ('paid', 'Paid', '#9acd32');