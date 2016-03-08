<?

	class Shop_HideDisabledShippingSwitcher extends Db_DataFilterSwitcher
	{
		public function applyToModel($model, $enabled, $context = null)
		{
			if ($enabled)
				$model->where('(enabled=1 OR backend_enabled = 1)');
			
			return $model;
		}
	}

?>