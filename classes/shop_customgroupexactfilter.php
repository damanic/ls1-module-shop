<?

	class Shop_CustomGroupExactFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_CustomGroup';
		public $list_columns = array('name');
		
		public function prepareListData()
		{
			$className = $this->model_class_name;
			$obj = new $className();

			return $obj;
		}

		public function applyToModel($model, $keys, $context = null)
		{
			$model->where('(exists (select shop_products.id from shop_products, shop_order_items, shop_products_customgroups where shop_products.id=shop_order_items.shop_product_id and shop_order_items.shop_order_id=shop_orders.id and shop_products_customgroups.shop_product_id=(if(shop_products.grouped is null or shop_products.grouped=0, shop_products.id, shop_products.product_id)) and shop_products_customgroups.shop_custom_group_id in (?)))', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			$keysStr = $this->keysToStr($keys);

			if ($context != 'totals')
				return "and (shop_custom_group.id in $keysStr)";
		}
	}

?>