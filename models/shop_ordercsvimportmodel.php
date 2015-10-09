<?

	class Shop_OrderCsvImportModel extends Backend_CsvImportModel
	{
		public $table_name = 'shop_orders';
		public $shipping_method_api_codes = array();
		
		public $has_many = array(
			'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_OrderCsvImportModel'", 'order'=>'id', 'delete'=>true),
			'config_import'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_OrderCsvImportModel'", 'order'=>'id', 'delete'=>true)
		);

		public function define_columns($context = null)
		{
			parent::define_columns($context);
		}
		
		public function define_form_fields($context = null)
		{
			parent::define_form_fields($context);
		}
		
		public function import_csv_data($data_model, $session_key, $column_map, $import_manager, $delimeter, $first_row_titles)
		{
			try
			{
				$added = 0;
				$skipped = 0;
				$skipped_rows = array();
				$updated = 0;
				$errors = array();
				$warnings = array();

				$csv_handle = $import_manager->csvImportGetCsvFileHandle();
				$column_definitions = $data_model->get_csv_import_columns();
				
				$first_row_found = false;
				$line_number = 0;
				while (($row = fgetcsv($csv_handle, 2000000, $delimeter)) !== FALSE)
				{
					$line_number++;
					if (Phpr_Files::csvRowIsEmpty($row))
						continue;
					
					if (!$first_row_found)
					{
						$first_row_found = true;
						if ($first_row_titles)
							continue;
					}
					try
					{
						$bind = array();
						$order_tracking_codes = false;
						$order_id = null;
						
						foreach ($column_map as $column_index=>$db_names)
						{
							if (!array_key_exists($column_index, $row))
								continue;

							$column_value = trim($row[$column_index]);
							
							foreach ($db_names as $db_name)
							{
								if (!array_key_exists($db_name, $column_definitions))
									continue;

								if ($db_name == 'id' && strlen($column_value))
								{
									//check that order exists, if not skip the row
									$existing_order_id = Db_DbHelper::scalar('select id from shop_orders where id=:id', array('id'=>$column_value));
									if(!strlen($existing_order_id))
									{
										$skipped++;
										$skipped_rows[$line_number] = 'Order does not exist: '.$column_value;
										continue 3;
									}
									else
										$order_id = $column_value;
								}
								elseif ($db_name == 'id' && !strlen($column_value))
								{
									$skipped++;
									$skipped_rows[$line_number] = 'Missiong order ID';
									continue 3;
								}

								if ($db_name == 'shop_order_shipping_track_codes' && strlen($column_value))
								{
									$order_tracking_codes = $this->get_tracking_codes($column_value, $warnings, $line_number);
								}
							}
						}
						if($order_id)
						{
							$updated++;
							if(is_array($order_tracking_codes))
								$this->update_tracking_codes($order_id, $order_tracking_codes);
						}
					} catch (Exception $ex)
					{
						$errors[$line_number] = $ex->getMessage();
					}
				}
			} catch (Exception $ex)
			{
				$errors[$line_number] = $ex->getMessage();
			}

			$result = array(
				'added'=>$added,
				'skipped'=>$skipped,
				'skipped_rows'=>$skipped_rows,
				'updated'=>$updated,
				'errors'=>$errors,
				'warnings'=>$warnings
			);
			return (object)$result;
		}

		public function update_tracking_codes($order_id, $tracking_codes)
		{
			$add_tracking_codes = $tracking_codes;
			$existing_tracking_codes = Db_DbHelper::queryArray('select id, shipping_method_id, code from shop_order_shipping_track_codes where order_id=:order_id', array('order_id' => $order_id));
			foreach($existing_tracking_codes as $index => $existing_code)
			{
				//check if it's in the array of imported codes - if not, delete it; if yes, remove it from codes to add to avoid readding it
				if(array_key_exists($existing_code['shipping_method_id'], $tracking_codes)
					&& array_key_exists($existing_code['code'], $tracking_codes[$existing_code['shipping_method_id']]))
				{
					unset($add_tracking_codes[$existing_code['shipping_method_id']][$existing_code['code']]);
				}
				else
					Db_DbHelper::query('delete from shop_order_shipping_track_codes where id=:id', array('id' => $existing_code['id']));
			}
			foreach($add_tracking_codes as $shipping_method_id => $codes_to_add)
			{
				foreach($codes_to_add as $code => $k)
				{
					$tracking_code_obj = new Shop_OrderTrackingCode();
					$tracking_code_obj->code_shipping_method = Shop_ShippingOption::create()->find_by_id($shipping_method_id);
					$tracking_code_obj->code = $code;
					$tracking_code_obj->order_id = $order_id;
					$tracking_code_obj->save();
				}
			}
		}

		public function get_tracking_codes($value, &$warnings, $line_number)
		{
			$result = array();
			if(!$this->shipping_method_api_codes)
			{
				$shipping_methods = Db_DbHelper::objectArray('select id, ls_api_code from shop_shipping_options where ls_api_code is not null');
				foreach($shipping_methods as $shipping_method)
				{
					$this->shipping_method_api_codes[trim(mb_strtolower($shipping_method->ls_api_code))] = $shipping_method->id;
				}
			}
			if(trim(mb_strtolower($value)) == 'none')
				return $result;
			$tracking_codes = explode(',', $value);
			if($value != '' && count($tracking_codes))
			{
				foreach($tracking_codes as $tracking_code)
				{
					$data = explode(':', $tracking_code);
					if(count($data) == 2)
					{
						$shipping_api_code = trim(mb_strtolower($data[0]));
						$code = trim($data[1]);
						if(array_key_exists($shipping_api_code, $this->shipping_method_api_codes))
							$result[$this->shipping_method_api_codes[$shipping_api_code]][$code] = true;
						else
							$this->add_warnings_line($warnings, $line_number, 'Shipping method with api code '.$shipping_api_code.' does not exist, tracking code '.$code.' was skipped.');
					}
					else
						$this->add_warnings_line($warnings, $line_number, 'Invalid shipping tracking code format: '.$tracking_code);
				}
			}
			return $result;
		}

		protected function add_warnings_line(&$warnings, $line, $message)
		{
			if (!array_key_exists($line, $warnings))
				$warnings[$line] = $message;
			else
				$warnings[$line] .= "\n".$message;
		}
	}

?>