alter table shop_option_matrix_records add column options_hash varchar(100);
create index product_options_hash on shop_option_matrix_records(product_id, options_hash);