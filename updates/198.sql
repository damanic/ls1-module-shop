alter table shop_company_information 
add column invoice_date_source varchar(20) default 'order_date',
add column invoice_due_date_interval int default 10;