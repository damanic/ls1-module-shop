alter table shop_order_payment_log
	add column ccv_response_code varchar(20),
	add column ccv_response_text varchar(255),
	add column avs_response_code varchar(20),
	add column avs_response_text varchar(255);