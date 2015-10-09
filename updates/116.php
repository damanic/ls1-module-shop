<?

	Db_DbHelper::query('alter table shop_products add column pt_description text');
	
	$products = Db_DbHelper::objectArray('select * from shop_products');
	foreach ($products as $product)
	{
		$description = html_entity_decode(strip_tags($product->description), ENT_QUOTES, 'UTF-8');

		Db_DbHelper::query('update shop_products set pt_description=:pt_description where id=:id', array(
			'pt_description'=>$description,
			'id'=>$product->id
		));
	}

?>
