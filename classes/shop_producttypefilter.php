<?

	class Shop_ProductTypeFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_ProductType';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context != 'product_list')
			{
				$model->where('(exists (select shop_products.id from shop_products, shop_order_items where shop_products.id=shop_order_items.shop_product_id and shop_order_items.shop_order_id=shop_orders.id and shop_products.product_type_id in (?)))', array($keys));
			} else
			{
				$model->where('product_type_id in (?)', array($keys));
			}
		}
		
		public function asString($keys, $context = null)
		{
			return 'and shop_products.product_type_id in '.$this->keysToStr($keys);
		}
	}

?>