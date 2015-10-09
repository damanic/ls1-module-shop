<?

	class Shop_DisplayInvoicesSwitcher extends Db_DataFilterSwitcher
	{
		public $list_columns = array('name');

		public function applyToModel($model, $enabled, $context = null)
		{
			if (!$enabled)
				$model->where('parent_order_id is null');
			
			return $model;
		}
	}

?>