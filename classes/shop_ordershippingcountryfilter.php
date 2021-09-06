<?

	class Shop_OrderShippingCountryFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Country';
		public $model_filters = 'enabled_in_backend=1';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			if(is_a($model,'Shop_Order')){
				$model->where('shop_orders.shipping_country_id IN (?)', array($keys));
			} else if ($model->belongs_to) {
				foreach($model->belongs_to as $field => $belongs_to_info){
					$class_name = isset($belongs_to_info['class_name']) ? $belongs_to_info['class_name'] : null;
					if($class_name == 'Shop_Order'){
						$foreign_key = $belongs_to_info['foreign_key'];
						if($foreign_key){
							$model->where($field.'_calculated_join.shipping_country_id IN (?)', array($keys));
						}
					}
				}
			}
		}
		
		public function asString($keys, $context = null)
		{
			return 'and shop_orders.shipping_country_id in '.$this->keysToStr($keys);
		}
	}

?>