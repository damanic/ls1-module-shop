<?

	class Shop_CouponFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Coupon';
		public $list_columns = array('code');
		
		public function applyToModel($model, $keys, $context = null)
		{
			if ($context == 'product_report')
			{
				$model->where('shop_orders.coupon_id in (?)', array($keys));
				return;
			}
			
			$model->where('coupon_id in (?)', array($keys));
		}
		
		public function asString($keys, $context = null)
		{
			return 'and coupon_id in '.$this->keysToStr($keys);
		}
	}

?>