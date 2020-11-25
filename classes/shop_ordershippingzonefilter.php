<?

	class Shop_OrderShippingZoneFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_ShippingZone';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			$country_ids = $this->get_country_ids($keys);
			if(!$country_ids){
				$country_ids = array('0'); //no countries, no results
			}
			if ( $context != 'product_report' ) {
				$model->where( 'shipping_country_id in (?)', array( $country_ids ) );
			} else {
				$model->where( 'shop_orders.shipping_country_id in (?)', array( $country_ids ) );
			}

		}
		
		public function asString($keys, $context = null)
		{
			$country_ids = $this->get_country_ids($keys);
			if(!$country_ids){
				$country_ids = array('0'); //no countries, no results
			}
			return 'and shop_orders.shipping_country_id IN '.$this->keysToStr($country_ids);
		}

		protected function get_country_ids($zone_keys){
			return Db_DbHelper::scalarArray('SELECT id FROM shop_countries WHERE shipping_zone_id IN '.$this->keysToStr($zone_keys));
		}
	}

?>