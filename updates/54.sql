alter table shop_payment_methods 
change thankyou_page_id receipt_page_id int;

update pages set action_reference='shop:payment_receipt' where action_reference='shop:payment_thankyou';