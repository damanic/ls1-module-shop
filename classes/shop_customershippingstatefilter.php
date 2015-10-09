<?

	class Shop_CustomerShippingStateFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_CountryState';
		public $list_columns = array('name');

		public function prepareListData()
		{
			$className = $this->model_class_name;
			$result = new $className();

			if ($this->model_filters)
				$result->where($this->model_filters);
				
			$result->where(' exists 
				(select 
					id 
				from 
					shop_countries 
				where 
					shop_countries.id=shop_states.country_id 
					and shop_countries.enabled_in_backend is not null 
					and shop_countries.enabled_in_backend=1)'
			);
			
			return $result;
		}

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context != 'product_report')
				$model->where('shipping_state_id in (?)', array($keys));
			else
				$model->where('shop_orders.shipping_state_id in (?)', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and shop_orders.shipping_state_id in '.$this->keysToStr($keys);
		}
	}

?>