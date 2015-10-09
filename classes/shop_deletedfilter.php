<?

	class Shop_DeletedFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_OrderDeletedStatus';
		public $list_columns = array('name');
		
		public function prepareListData()
		{
			$className = $this->model_class_name;
			$obj = new $className();

			return $obj;
		}

		public function applyToModel($model, $keys, $context = null)
		{
			$codes = array();
			foreach ($keys as $id)
			{
				$code = Shop_OrderDeletedStatus::code_by_id($id);
				if ($code)
					$codes[] = $code;
			}

			if (in_array('active', $codes))
				$model->where('deleted_at is null');
			elseif (in_array('deleted', $codes))
				$model->where('deleted_at is not null');
		}
	}

?>