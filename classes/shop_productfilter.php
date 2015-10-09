<?

	class Shop_ProductFilter extends Db_DataFilter
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
			if ($context == 'product_report')
			{
				$model->where('shop_products.id in (?)', array($keys));
				return;
			}
			
			$model->where('(exists (select shop_products.id from shop_products, shop_order_items where shop_products.id=shop_order_items.shop_product_id and shop_order_items.shop_order_id=shop_orders.id and shop_products.id in (?)))', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and shop_order_items.shop_product_id in '.$this->keysToStr($keys);
		}
	}

?>