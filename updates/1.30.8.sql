alter table shop_orders add column currency_code VARCHAR(4);
alter table shop_orders add column shop_currency_rate DECIMAL(18,6) DEFAULT 1;