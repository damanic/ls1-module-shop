create index shipping_street_addr on shop_customers(shipping_street_addr);
create index billing_street_addr on shop_customers(billing_street_addr);
create index shipping_city on shop_customers(shipping_city);
create index billing_city on shop_customers(billing_city);
create index name on shop_countries(name);
create index name on shop_states(name);