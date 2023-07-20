UPDATE shop_cart_rules
SET action_class_name = 'Shop_CartBuyMGetNDiscounted_Action'
WHERE action_class_name = 'Shop_CartBuyMGetNFree_Action';