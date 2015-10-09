<?
	@set_time_limit(3600);

	function update_252_add_tax_item(&$list, $name, $amount, $default_name = 'tax')
	{
		if (!$name)
			$name = $default_name;
		
		if (!array_key_exists($name, $list))
		{
			$tax_info = array('name'=>$name, 'total'=>0);
			$list[$name] = (object)$tax_info;
		}
		
		$list[$name]->total += $amount;
	}

	$orders = Db_DbHelper::objectArray('select id from shop_orders where goods_tax > 0');
	foreach ($orders as $order)
	{
		$items = Db_DbHelper::objectArray('select * from shop_order_items where shop_order_id=:id', array(
			'id'=>$order->id
		));
		
		$order_taxes = array();
		foreach ($items as $item)
		{
			if ($item->tax > 0)
				update_252_add_tax_item($order_taxes, $item->tax_name_1, ($item->tax-$item->tax_discount_1)*$item->quantity, 'Sales tax');
				
			if ($item->tax_2 > 0)
				update_252_add_tax_item($order_taxes, $item->tax_name_2, ($item->tax_2-$item->tax_discount_2)*$item->quantity, 'Sales tax');
		}
		
		$taxes_to_save = array();
		foreach ($order_taxes as $name=>$tax_data)
		{
			$tax_data->total = round($tax_data->total, 2);
			$taxes_to_save[$name] = $tax_data;
		}

		$taxes_to_save = serialize($taxes_to_save);
		Db_DbHelper::query('update shop_orders set sales_taxes=:sales_taxes, goods_tax=(goods_tax-ifnull(tax_discount, 0)) where id=:id', array(
			'id'=>$order->id,
			'sales_taxes'=>$taxes_to_save,
		));
	}
?>