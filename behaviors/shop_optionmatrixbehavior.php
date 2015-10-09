<?php

	/**
	 * This behavior provides user interface for managing product Option Matrix records.
	 */
	class Shop_OptionMatrixBehavior extends Phpr_ControllerBehavior
	{
		protected $product = null;
		protected $product_options = null;
		
		protected $data_row_options = array();
		
		public function __construct($controller)
		{
			parent::__construct($controller);
			
			if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create')
			{
				$this->addGlobalEventHandler('onLoadOptionMatrixPopup');
				$this->addGlobalEventHandler('onSaveOptionMatrix');
				$this->addGlobalEventHandler('onCancelOptionMatrix');

				Backend::$events->addEvent('core:onInitFormWidgetModel', $this, 'on_init_model');
				Backend::$events->addEvent('core:onPrepareFormGridWidgetDataPage', $this, 'on_data_page');
				Backend::$events->addEvent('shop:onGenerateOptionMatrixRecords', $this, 'on_generate_records');
			}
		}
		
		public function onLoadOptionMatrixPopup($product_id = null)
		{
			try
			{
				$product = $this->load_product_object($product_id);
				$form_width = post('width') ? post('width') : 850;
				$form_width -= 140;
				
				$this->viewData['form_width'] = $form_width;
				$model = $this->init_matrix_model($product, true, true);
				$this->viewData['form_model'] = $model;
				
				$post_data = post('Shop_Product');
				$product->sku = $post_data['sku'];
				$product->name = $post_data['name'];
				$product->price = $post_data['price'];
				
				$this->viewData['product'] = $product;
				$this->viewData['grouped_product_id'] = post('grouped_product_id');
				$this->viewData['grouped_product'] = post('grouped_product');
			}
			catch (exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}
			
			$this->renderPartial('option_matrix_popup');
		}
		
		public function onSaveOptionMatrix($product_id = null)
		{
			try
			{
				$model = Shop_OptionMatrixRecord::create();
				$model->init_columns_info();
				$model->define_form_fields('grid');
				
				$image_fields = $this->list_image_fields($model);
				
				$grid_data_field = $model->find_form_field('grid_data');
				
				$product = $this->load_product_object($product_id);
				
				/*
				 * Commit data to the data source
				 */
				
				$grid_widget = $this->_controller->formInitWidget('grid_data', $model);

				$grid_data = Phpr::$request->post_array_item('Shop_OptionMatrixRecord', 'grid_data', array());
				$data_source = $grid_widget->get_data_source();
				$data_source->commit($grid_data);
				
				/*
				 * Validate data
				 */
				
				$grid_data = $data_source->get_data();
				$grid_data_processed = array();

				$record_index = -1;
				foreach ($grid_data as $row_index=>&$row)
				{
					$record_index++;

					/*
					 * Skip empty rows
					 */
					
					$external_row_data = Db_GridWidgetDataSource::get_row_data($row);

					$data_found = false;
					foreach ($external_row_data as $field_name=>$field_data)
					{
						if (strlen(trim($field_data)) && ($field_name != 'images' || $field_data != '0 images'))
						{
							$data_found = true;
							continue;
						}
					}
					
					if (!$data_found)
						continue;
					
					/*
					 * Validate option values
					 */
					foreach ($row as $field_name=>$field_data)
					{
						if (substr($field_name, 0, 7) == 'option|' && strpos($field_name, '_internal') === false)
						{
							$field_data = trim(Db_GridWidgetDataSource::get_field_data($field_data));
							if (!strlen($field_data))
							{
								$option_data = explode('|', $field_name);
								$option = Shop_CustomAttribute::create()->find($option_data[1]);
								if ($option)
									$this->grid_error($model, 'grid_data', 'Please specify value for "'.$option->name.'" option.', $row_index, $field_name, $record_index);
								else
									$this->grid_error($model, 'grid_data', 'Please specify option value.', $row_index, $field_name, $record_index);
							}
						}
					}
					
					/*
					 * Validate other columns
					 */
					
					foreach ($grid_data_field->renderOptions['columns'] as $column_name=>$column_options)
					{
						if (!isset($row[$column_name]))
							continue;
						
						$validation_type = isset($column_options['validation_type']) ? $column_options['validation_type'] : null;
						if (!$validation_type)
							continue;

						$value = Db_GridWidgetDataSource::get_field_data($row[$column_name]);

						if (!strlen($value) && $column_name != 'sale_price_or_discount')
							continue;

						switch ($validation_type)
						{
							case db_float :
								if (!Core_Number::is_valid($value))
									$this->grid_error($model, 'grid_data', 'Invalid numeric value in '.$column_options['title'].' column: '.$value, $row_index, $column_name, $record_index);
							break;
							case db_number :
								if (!preg_match("/^\-?[0-9]+$/", $value))
									$this->grid_error($model, 'grid_data', 'Invalid integer value in '.$column_options['title'].' column: '.$value, $row_index, $column_name, $record_index);
							break;
							case db_date :
								if (!preg_match('/[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $value))
								{
									$date = Phpr_DateTime::parse($value, '%x');
									if (!$date)
										$this->grid_error($model, 'grid_data', 'Invalid date value in '.$column_options['title'].' column: '.$value, $row_index, $column_name, $record_index);

									$row[$column_name] = $date->toSqlDate();
								}
							break;
							case 'discount' :
								$base_price_value = null;
								if ($column_name == 'sale_price_or_discount')
								{
									$base_price_value = isset($row['base_price']) ? Db_GridWidgetDataSource::get_field_data($row['base_price']) : null;
									$on_sale_value = isset($row['on_sale']) ? Db_GridWidgetDataSource::get_field_data($row['on_sale']) : null;

									if (!strlen($value) && $on_sale_value)
										$this->grid_error($model, 'grid_data', 'Please specify a sale price or discount or uncheck the "On Sale" checkbox.', $row_index, $column_name, $record_index);
								}

								if ($error = Shop_Product::is_sale_price_or_discount_invalid($value, $base_price_value))
									$this->grid_error($model, 'grid_data', $error, $row_index, $column_name, $record_index);
							break;
						}
					}
						
					$grid_data_processed[$row_index] = $row;
				}
				
				$grid_data = $grid_data_processed;
				unset($grid_data_processed);
				
				/*
				 * Save data
				 */
				 
				$matrix_record_model = Shop_OptionMatrixRecord::create(); // Reusable model
				$updated_ids = array();
				$db_base = new Db_Base();

				foreach ($grid_data as $row_index=>&$row)
				{
					/*
					 * Add or update records
					 */
					
					$is_new_record = $row_index < 1;
					$row['id'] = $is_new_record ? 0 : $row_index;
					$skip_fields = array();
					
					if (!$is_new_record) 
						$skip_fields[] = 'product_id';
					
					foreach ($image_fields as $image_field)
						unset($row[$image_field]); // Remove images column from the data row, otherwise it breaks the images relation.

					$matrix_record_model->reinitialize($is_new_record ? null : $row_index, $skip_fields);
					
					/*
					 * Save the record and add it to the product
					 */
					
					$external_row_data = Db_GridWidgetDataSource::get_row_data($row);
					if (isset($row['base_price']))
						$external_row_data['tier_price_compiled'] = trim(Db_GridWidgetDataSource::get_field_data($row['base_price'], true));
					else
						$external_row_data['tier_price_compiled'] = null;

					$matrix_record_model->save($external_row_data, $this->_controller->formGetEditSessionKey().'-'.$row_index);
					if ($is_new_record)
						$product->option_matrix_records->add($matrix_record_model, $this->_controller->formGetEditSessionKey());

					$updated_ids[] = $matrix_record_model->id;
						
					/*
					 * Update record options
					 */

					if (!$is_new_record)
						Db_DbHelper::query('delete from shop_option_matrix_options where matrix_record_id=:id', array('id'=>$matrix_record_model->id));

					$option_values = array();
					$options_update_sql = 'insert into shop_option_matrix_options (matrix_record_id, option_id, option_value) values ';

					foreach ($external_row_data as $field_name=>$field_value)
					{
						/*
						 * Save option/value links
						 */

						if (substr($field_name, 0, 7) == 'option|' && strpos($field_name, '_internal') === false)
						{
							$option_data = explode('|', $field_name);
							
							$option_values[Shop_CustomAttribute::get_name_by_id($option_data[1])] = $field_value;
							
							$options_update_sql .= $db_base->prepare('(:record_id, :option_id, :option_value)', array(
									'record_id'=>$matrix_record_model->id,
									'option_id'=>$option_data[1], 
									'option_value'=>$field_value
							)).',';
						}
					}

					Db_DbHelper::query(substr($options_update_sql, 0, -1));

					/*
					 * Set record option hash values
					 */

					if ($option_values)
						$matrix_record_model->set_options_hash(Shop_OptionMatrixRecord::generate_options_hash($option_values, false));
				}
				
				/*
				 * Delete removed records
				 */
				
				if (!count($updated_ids))
					$updated_ids[] = -1;
					
				if (!$product->is_new_record())
				{
					$bind = array('product_id'=>$product->id, 'updated'=>$updated_ids);
					$ids_to_delete = Db_DbHelper::scalarArray('select id from shop_option_matrix_records where product_id=:product_id and id not in (:updated)', $bind);
					
					if (count($ids_to_delete) > 0)
					{
						foreach ($ids_to_delete as $id)
						{
							$matrix_record_model->id = $id;
							$product->option_matrix_records->delete($matrix_record_model, $this->_controller->formGetEditSessionKey());
						}
					}
				}
				
				/*
				 * Remove data source from the session.
				 */
				
				Db_GridWidgetDataSource::dispose_session_data($this->_controller->formGetEditSessionKey());
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onCancelOptionMatrix($product_id = null)
		{
			Db_DeferredBinding::cancelDeferredActionsSubSession('Shop_OptionMatrixRecord', $this->_controller->formGetEditSessionKey().'-');
			Db_GridWidgetDataSource::dispose_session_data($this->_controller->formGetEditSessionKey());
		}
		
		protected function grid_error($model, $field, $message, $grid_row = null, $grid_column = null, $record_index = null)
		{
			if ($grid_row != null) 
			{
				$page_index = ceil(($record_index+1)/post('page_size'))-1;
				if ($page_index < 0)
					$page_index = 0;

				$model->validation->setWidgetData(Db_GridWidget::get_cell_error_data($model, 'grid_data', $grid_column, $grid_row, $page_index));
			}
			
			$model->validation->setError($message, $field, true);
			$model->validation->throwException();
		}
		
		protected function init_matrix_model($product, $load_matrix_data = false, $initialization = false)
		{
			$model = Shop_OptionMatrixRecord::create();
			$this->init_matrix_columns($this->_controller, $model, $initialization);

			if ($load_matrix_data)
			{
				$grid_widget = $this->_controller->formInitWidget('grid_data', $model);
				$data_source = $grid_widget->get_data_source();
				$grid_data = array();

				$options = $this->load_product_options($product, $this->_controller);

				$record_obj = $product->get_related_records_deferred_obj('option_matrix_records', $this->_controller->formGetEditSessionKey());
				$record_obj->addColumn('
						(select 
							count(*)
						from 
							shop_order_items, 
							shop_orders 
						where 
							option_matrix_record_id is not null 
							and option_matrix_record_id=shop_option_matrix_records.id 
							and shop_orders.id = shop_order_items.shop_order_id) as has_orders
					');
					
				$option_values = array();
				foreach ($options as $option)
				{
					$record_obj->addColumn('null as option_'.$option->id);
					
					$cur_option_values = Db_DbHelper::queryArray("select 
						shop_option_matrix_options.option_value,
						shop_option_matrix_records.id as record_id
					from 
						shop_option_matrix_options,
						shop_option_matrix_records
					where 
						matrix_record_id=shop_option_matrix_records.id
						and (
							shop_option_matrix_records.product_id=:product_id or (
								exists (
									select 
										* 
									from 
										db_deferred_bindings 
									where detail_key_value=shop_option_matrix_records.id 
									and master_relation_name='option_matrix_records' 
									and master_class_name='Shop_Product' 
									and is_bind=1 and session_key=:session_key
								))) and (not (exists (
									select 
										* 
									from 
										db_deferred_bindings 
									where 
										detail_key_value=shop_option_matrix_records.id 
										and master_relation_name='option_matrix_records' 
										and master_class_name='Shop_Product' 
										and is_bind=0 and session_key=:session_key
										and id > ifnull((
											select max(id) from db_deferred_bindings where 
											detail_key_value=shop_option_matrix_records.id 
											and master_relation_name='option_matrix_records' 
											and master_class_name='Shop_Product' and is_bind=1 and session_key=:session_key), 0)
							)))
						and option_id=:option_id", array(
							'product_id'=>$product->id,
							'option_id'=>$option->id,
							'session_key'=>$this->_controller->formGetEditSessionKey()
						));
						
					$option_values[$option->id] = array();
					foreach ($cur_option_values as $option_value_data)
					{
						$record_id = $option_value_data['record_id'];
						$option_values[$option->id][$record_id] = $option_value_data['option_value'];
					}
				}

				$matrix_data = Db_DbHelper::queryArray($record_obj->build_sql());

				foreach ($matrix_data as $data_row)
				{
					$data_row_sanitized = $data_row;
					foreach ($data_row as $key=>$value)
					{
						if (substr($key, 0, 7) == 'option_')
						{
							unset($data_row_sanitized[$key]);
							$key_data = explode('_', $key);
							
							if (isset($option_values[$key_data[1]][$data_row['id']]))
								$value = $option_values[$key_data[1]][$data_row['id']];
							
							$data_row_sanitized['option|'.$key_data[1]] = $value;
						}
					}
					$data_row_sanitized['base_price'] = array($data_row_sanitized['base_price'], $data_row_sanitized['tier_price_compiled']);
					
					if ($data_row['has_orders'])
						$data_row_sanitized['grid_block_delete_message'] = 'This record cannot be deleted because there are orders referring to it.';
						
					$grid_data[$data_row['id']] = $data_row_sanitized;
				}

				$data_source->set_data($grid_data);
			}
			
			return $model;
		}
		
		public function on_init_model($controller, $model)
		{
			if (get_class($model) == 'Shop_OptionMatrixRecord')
				$this->init_matrix_columns($controller, $model);
		}
		
		public function on_data_page($data_source, $data_page)
		{
			if (get_class($data_source->widget->model) != 'Shop_OptionMatrixRecord')
				return;
				
			$model = Shop_OptionMatrixRecord::create();
			
			if (!($field = $model->find_form_field('grid_data')))
			{
				$model->init_columns_info();
				$model->define_form_fields();
			}
			
			$image_fields = $this->list_image_fields($model);

			foreach ($data_page as $row_index=>&$row_data)
			{
				foreach ($image_fields as $image_field)
				{
					$row_model = $model->get_record_cached($row_index < 0 ? null : $row_index);
					$row_model->init_columns_info();
					
					if ($row_model)
					{
						$obj = $row_model->get_related_records_deferred_obj($image_field, $this->_controller->formGetEditSessionKey().'-'.$row_index);
						$count = $obj->request_row_count();
					} else 
						$count = 0;

					$row_data[$image_field] = $count == 0 ? '0 images' : $count.' image(s)';
				}
			}
			
			return $data_page;
		}
		
		protected function list_image_fields($model)
		{
			$field = $model->find_form_field('grid_data');
			$image_fields = array();

			foreach ($field->renderOptions['columns'] as $column_key=>$column)
			{
				if (isset($column['editor_class']) && $column['editor_class'] == 'Db_GridImagesEditor')
					$image_fields[] = $column_key;
			}

			return $image_fields;
		}
		
		protected function load_product_object($product_id = null)
		{
			if ($this->product !== null)
				return $this->product;

			if (!post('grouped_product'))
			{
				if ($product_id === null)
				{
					$product_id = Phpr::$router->param('param1');
					if (!preg_match('/^[0-9]+$/', $product_id))
						$product_id = null;
				}
			} else
				$product_id = post('grouped_product_id');
			
			$product = Shop_Product::create();
			if ($product_id)
			{
				$product = $product->find($product_id);
				if (!$product)
					throw new Phpr_ApplicationException('Product not found');
			}

			return $this->product = $product;
		}

		protected function init_matrix_columns($controller, $model, $initialization = false)
		{
			$product = $this->load_product_object();
			
			if (!($field = $model->find_form_field('grid_data')))
			{
				$model->init_columns_info();
				$model->define_form_fields();
			}
			
			$image_fields = $this->list_image_fields($model);
			foreach ($image_fields as $image_field)
				$model->delete_form_field($image_field);
			
			$field = $model->find_form_field('grid_data');
			$options = $this->load_product_options($product, $controller);
			$columns = array();
			$total_width = isset($this->viewData['form_width']) ? $this->viewData['form_width'] : post('form_width');
			$total_width -= count($field->renderOptions['columns']) + $options->count + 2; // Reduce the total width by borders width
			
			$api_columns_width = 0;
			foreach ($field->renderOptions['columns'] as $column)
				$api_columns_width += isset($column['width']) ? $column['width'] : 100;
			
			$option_column_width = round(($total_width - $api_columns_width)/$options->count);
			if ($option_column_width < 150)
			{
				$field->renderOptions['table_width'] = $api_columns_width + $options->count*150;
				$option_column_width = 150;
			}

			foreach ($options as $option)
			{
				$option_key = 'option|'.$option->id;
				$option_keys = array();

				foreach ($option->list_values() as $value)
					$option_keys[] = $value;
				
				$columns[$option_key] = array(
					'title'=>$option->name, 
					'type'=>'dropdown', 
					'option_keys'=>$option_keys, 
					'option_values'=>$option_keys,
					'width'=>$option_column_width,
					'cell_css_class'=>'key',
					'column_group'=>'Options'
				);
			}
			
			$field->renderOptions['columns'] = array_merge($columns, $field->renderOptions['columns']);
			if ($initialization && post('height'))
			{
				$field->renderOptions['page_size'] = floor((post('height') - 350)/30);
				if ($field->renderOptions['page_size'] < 4)
					$field->renderOptions['page_size'] = 4;
					
				$this->viewData['page_size'] = $field->renderOptions['page_size'];
			}
			
			if (post('page_size'))
				$field->renderOptions['page_size'] = post('page_size');
				
			$field->renderOptions['help_partial_path'] = PATH_APP.'/modules/shop/behaviors/shop_optionmatrixbehavior/partials/_grid_help.htm';
			$field->renderOptions['toolbar_partial_path'] = PATH_APP.'/modules/shop/behaviors/shop_optionmatrixbehavior/partials/_toolbar_buttons.htm';
		}
		
		protected function load_product_options($product, $controller)
		{
			if ($this->product_options !== null)
				return $this->product_options;
			
			$options = $product->list_related_records_deferred('options', $controller->formGetEditSessionKey());
			if (!$options->count)
				throw new Phpr_ApplicationException('You have not defined any product options. Please define options first.');
				
			return $this->product_options = $options;
		}
		
		public function on_generate_records($grid_widget, $data_source)
		{
			set_time_limit(3600);

			$product = $this->load_product_object();
			$options = $this->load_product_options($product, $this->_controller);
		
			$rows = array();
			$data_index = 0;
			$data_stack = array();
			$this->get_option_values($options, 0, $data_stack, $data_index, $rows);
			$records_added = post('phpr_grid_records_added', 1);

			$rows_added = 0;
			$this->data_row_options = array();
			
			if ($options->count && $rows)
			{
				$grid_data = $data_source->get_data();

				// The quick array search approach (see exists_in_array()) is used for determining whether
				// a row with a specific combination of options already exists in the data source. 
				// The array contains hashes of serialized options. The array should be sorted
				// in order the algorithm to work. 

				$grid_data_hash = array();
				$first_available_index = 0;
				foreach ($grid_data as $row_index=>$data_row)
				{
					$row_options_data = $this->get_datasource_row_options_data($data_row, $row_index);
					$grid_data_hash[] = md5(serialize($row_options_data));
					
					if ($row_index < 0 && $first_available_index > $row_index)
						$first_available_index = $row_index;
				}
				
				sort($grid_data_hash);
				
				foreach ($rows as $row)
				{
					$row_options = array();
					foreach ($row as $option_id=>$option_value)
						$row_options[$option_id] = $option_value;
					$row_options_hash = md5(serialize($row_options));
					
					if (!$this->exists_in_array($row_options_hash, $grid_data_hash, 0, count($grid_data_hash)-1))
					{
						$data_row = array();
						foreach ($row as $option_id=>$option_value)
							$data_row['option|'.$option_id] = $option_value;
						
						$grid_data[-1*$records_added + $first_available_index] = $data_row;
						$records_added++;
						$rows_added++;
					}
				}

				$data_source->set_data($grid_data);
			}

			$grid_widget->set_message($rows_added.' row(s) have been added.');
		}
		
		protected function exists_in_array(&$needle, &$haystack, $start, $end) 
		{ 
			if($end < $start) 
				return false; 

			$mid = (int)(($end - $start) / 2) + $start; 

			if($haystack[$mid] > $needle) 
				return $this->exists_in_array($needle, $haystack, $start, $mid - 1); 

			if($haystack[$mid] < $needle) 
				return $this->exists_in_array($needle, $haystack, $mid + 1, $end); 

			return true; 
		}

		protected function get_datasource_row_options_data(&$data_row, $row_index)
		{
			if (isset($this->data_row_options[$row_index]))
				return $this->data_row_options[$row_index];
			
			$result = array();
			
			foreach ($data_row as $field_name=>$field_data)
			{
				if (substr($field_name, 0, 7) == 'option|' && strpos($field_name, '_internal') === false)
				{
					$option_value = trim(Db_GridWidgetDataSource::get_field_data($field_data));
					$option_data = explode('|', $field_name);
					$option_id = $option_data[1];
					
					$result[$option_id] = $option_value;
				}
			}
			
			$this->data_row_options[$row_index] = $result;
			
			return $result;
		}
		
		protected function get_option_values($options, $option_index, &$data_stack, &$data_index, &$result)
		{
			if (!array_key_exists($data_index, $result))
				$result[$data_index] = array();

			$option = $options[$option_index];
			$option_values = $option->list_values();

			foreach ($option_values as $value) 
			{
				$data_stack[$option->id] = $value;

				if ($option_index < $options->count()-1)
					$this->get_option_values($options, $option_index+1, $data_stack, $data_index, $result);
				else
				{
					foreach ($data_stack as $option_id=>$option_value)
						$result[$data_index][$option_id] = $option_value;
						
					$data_index++;
				}
			}
		}
	}
	
?>