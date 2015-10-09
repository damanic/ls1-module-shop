<?

	class Shop_OrderStatusFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_OrderStatus';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			if ($context == 'product_report')
			{
				$model->where('shop_orders.status_id in (?)', array($keys));
				return;
			}

			$model->where('status_calculated_join.id in (?)', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and status_id in '.$this->keysToStr($keys);
		}
	}

?>