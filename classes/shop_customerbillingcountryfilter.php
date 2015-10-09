<?

	class Shop_CustomerBillingCountryFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_Country';
		public $model_filters = 'enabled_in_backend=1';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			$model->where('billing_country_id in (?)', array($keys));
		}
	}

?>