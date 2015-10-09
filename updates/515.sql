alter table shop_products 
  add column product_rating float,
  add column product_rating_all float,
  add column product_rating_review_num int,
  add column product_rating_all_review_num int;

update shop_products set 
  product_rating=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status='approved'),
  product_rating_all=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id),
  product_rating_review_num=ifnull((select count(*) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status='approved'), 0),
  product_rating_all_review_num=ifnull((select count(*) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id), 0);