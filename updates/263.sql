alter table shop_order_items 
change column price price decimal(15,6),
change column extras_price extras_price decimal(15,6),
change column tax tax decimal(15,6),
change column discount discount decimal(15,6),
change column tax_2 tax_2 decimal(15,6);