<?

	class Shop_CustomerGroupFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_CustomerGroup';
		public $list_columns = array('name');
		
		public function prepareListData()
		{
			$className = $this->model_class_name;
			$obj = new $className();

			return $obj;
		}

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context == 'product_report')
			{
				$model->where('shop_customers.customer_group_id in (?)', array($keys));
				return;
			}

			if ($model instanceof Shop_Customer)
				$model->where('customer_group_id in (?)', array($keys));
			else
				$model->where('customer_calculated_join.customer_group_id in (?)', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and customer_group_id in '.$this->keysToStr($keys);
		}
	}

?>