<?

	class Shop_OrderSKUFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Product';
		public $list_columns = array('name', 'sku');
		
		public function prepareListData()
		{
			$className = $this->model_class_name;
			$obj = new $className();
			$obj->where('((shop_products.grouped is null or shop_products.grouped=0) or (shop_products.grouped=1 and shop_products.product_id is not null))');
			return $obj;
		}

		public function applyToModel($model, $keys, $context = null)
		{
			$skus = Db_DbHelper::scalarArray("SELECT sku FROM shop_products WHERE id IN (?)", array($keys));


			if ($context == 'product_report') {
				$model->where('COALESCE(shop_option_matrix_records.sku, shop_products.sku) IN (?)', array($skus));
				return;
			}

			$model->where('( EXISTS 
								(
									SELECT COALESCE(shop_option_matrix_records.sku, shop_products.sku) AS product_sku 
									FROM shop_order_items 
									LEFT JOIN shop_products
									ON shop_order_items.shop_product_id = shop_products.id
									LEFT JOIN shop_option_matrix_records
									ON shop_order_items.option_matrix_record_id = shop_option_matrix_records.id
									WHERE shop_orders.id = 	shop_order_items.shop_order_id
									AND COALESCE(shop_option_matrix_records.sku, shop_products.sku) IN (?) 
								)
							)', array($skus));
		}
		
		public function asString($keys, $context = null)
		{
			$skus = Db_DbHelper::scalarArray("SELECT sku FROM shop_products WHERE id IN (?)", array($keys));


			if ($context == 'product_report') {
				return 'AND (COALESCE(shop_option_matrix_records.sku, shop_products.sku) IN  '.$this->keysToStr($skus).')';
			}

			return 'AND (
							EXISTS 
								(
									SELECT COALESCE(shop_option_matrix_records.sku, shop_products.sku) AS product_sku 
									FROM shop_order_items 
									LEFT JOIN shop_products
									ON shop_order_items.shop_product_id = shop_products.id
									LEFT JOIN shop_option_matrix_records
									ON shop_order_items.option_matrix_record_id = shop_option_matrix_records.id
									WHERE shop_orders.id = 	shop_order_items.shop_order_id
									AND COALESCE(shop_option_matrix_records.sku, shop_products.sku) IN  '.$this->keysToStr($skus).' 
								)
						)';
		}
	}

?>