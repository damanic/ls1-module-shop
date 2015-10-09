<?

	class Shop_OrderShippingCountryFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Country';
		public $model_filters = 'enabled_in_backend=1';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context != 'product_report')
				$model->where('shipping_country_id in (?)', array($keys));
			else
				$model->where('shop_orders.shipping_country_id in (?)', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and shop_orders.shipping_country_id in '.$this->keysToStr($keys);
		}
	}

?>