<? 

	class Shop_ProductExportLs2 extends Shop_ProductExport
	{
		public static function export_csv($iwork = false, $columns_override = NULL, $write_to_file = false, $include_images = false)
		{
			set_time_limit(3600);
			
			if ($include_images)
				$write_to_file = PATH_APP.'/temp/export.csv';
			
			if (!$write_to_file)
				self::write_download_headers('text/csv', 'products.csv');

			$columns = Shop_Product::create()->get_csv_import_columns_ls2(false);
			
			if (!$include_images && isset($columns['images']))
				unset($columns['images']);

			$header = array();
			foreach ($columns as $column)
				$header[] = strlen($column->listTitle) ? $column->listTitle : $column->displayName;

			$separator = ',';
			
			$file_handle = null;
			if ($write_to_file)
			{
				$file_handle = @fopen($write_to_file, 'w');
				if (!$file_handle)
					throw new Phpr_ApplicationException('Cannot open/create file for writing');
			}
			
			$images_to_export = array();
			try
			{
				$data = Phpr_Files::outputCsvRow($header, $separator, $write_to_file);
				if ($write_to_file)
					@fwrite($file_handle, $data);
			
				$products = new Shop_Product(null, array('no_column_init'=>true, 'no_validation'=>true));
				$grouped_product = new Shop_Product(null, array('no_column_init'=>true, 'no_validation'=>true));
				$om_record = new Shop_OptionMatrixRecord(null, array('no_column_init'=>true, 'no_validation'=>true));
				$query = "SELECT 
					shop_products.*, 
					(select group_concat(CONCAT(IF(db_files.is_public, 'public/', ''), db_files.disk_name, '|', db_files.name) ORDER BY 1 SEPARATOR '\n') from db_files where db_files.master_object_id = shop_products.id and (master_object_class='Shop_Product' and field='images')) AS images,
					(select group_concat(grouped_option_desc SEPARATOR '|') FROM shop_products AS sp WHERE sp.product_id = shop_products.id) AS grouped_products_option_desc,
					((select count(product_id) FROM shop_products AS sp WHERE sp.product_id = shop_products.id) > 0) as has_grouped_products
					FROM shop_products WHERE shop_products.product_id IS NULL
				";
				
				$gp_records_query = "SELECT 
					shop_products.*, 
					(select group_concat(CONCAT(IF(db_files.is_public, 'public/', ''), db_files.disk_name, '|', db_files.name) ORDER BY 1 SEPARATOR '\n') from db_files where db_files.master_object_id = shop_products.id and (master_object_class='Shop_Product' and field='images')) AS images
					FROM shop_products WHERE product_id=:product_id ORDER BY id
				";
				
				$om_records_query = "SELECT 
					shop_option_matrix_records.*,
					(select group_concat(CONCAT(IF(db_files.is_public, 'public/', ''), db_files.disk_name, '|', db_files.name) ORDER BY 1 SEPARATOR '\n') from db_files where db_files.master_object_id = shop_option_matrix_records.id and (master_object_class='Shop_OptionMatrixRecord' and field='images')) AS images
					FROM shop_option_matrix_records where product_id=:product_id order by id
				";
				
				$om_record->init_columns_info();
				$om_record->define_form_fields();
				$grid_data_field = $om_record->find_form_field('grid_data');
				$option_matrix_columns = $grid_data_field->renderOptions['columns'];

				$list_data = Db_DbHelper::queryArray($query);
				global $activerecord_no_columns_info;
				$activerecord_no_columns_info = true;
			
				$sku_index = array();
				foreach ($list_data as $row_data)
					$sku_index[$row_data['id']] = $row_data['sku'];

				$images_to_export = array();
				$base_image_export_path = 'images';
				
				foreach ($list_data as $row_data)
				{
					/*
					 * Output product row
					 */
					
					$row = self::format_product_row($products, $row_data, $columns, $sku_index);
					$row = self::get_images_for_export($row, $base_image_export_path, $images_to_export);
					
					if ($row_data['grouped_products_option_desc'])
					{
						$master_row = array_merge($row, array(
							'options' => ($row_data['grouped_attribute_name'].':'.$row_data['grouped_option_desc'].'|'.$row_data['grouped_products_option_desc']),
							'sku' => $row_data['sku'].'-master'
						));
						$data = Phpr_Files::outputCsvRow($master_row, $separator, $write_to_file);
						if ($write_to_file)
							@fwrite($file_handle, $data);
						
						$row['product_variant_flag'] = 1;
						$row['csv_import_parent_sku'] = $row['sku'].'-master';
					}
					
					$data = Phpr_Files::outputCsvRow($row, $separator, $write_to_file);
					if ($write_to_file)
						@fwrite($file_handle, $data);

					if ($row_data['grouped_products_option_desc'])
					{
						/*
						 * Output Grouped Product rows
						 */
						$gp_query_resource = Db_DbHelper::query($gp_records_query, array('product_id'=>$row_data['id']));
						while ($gp_row_data = Db_DbHelper::fetch_next($gp_query_resource))
						{
							$gp_row_data = array_merge($gp_row_data, array(
								'product_variant_flag'          => 1,
								'parent_grouped_attribute_name' => $row_data['grouped_attribute_name'],
							));
							
							$gp_row = self::format_product_row($grouped_product, $gp_row_data, $columns, $sku_index);
							$gp_row = self::get_images_for_export($gp_row, $base_image_export_path, $images_to_export);
						
							$data = Phpr_Files::outputCsvRow($gp_row, $separator, $write_to_file);
							if ($write_to_file)
								@fwrite($file_handle, $data);
						}
						Db_DbHelper::free_result($gp_query_resource);
					} else
					{
					/*
					 * Output Option Matrix rows
					 */
						$om_query_resource = Db_DbHelper::query($om_records_query, array('product_id'=>$row_data['id']));
						while ($om_row_data = Db_DbHelper::fetch_next($om_query_resource))
						{
							$om_row = self::format_om_row($row_data['sku'], $om_record, $om_row_data, $columns, $option_matrix_columns);
							$om_row = self::get_images_for_export($om_row, $base_image_export_path, $images_to_export);
						
							$data = Phpr_Files::outputCsvRow($om_row, $separator, $write_to_file);
							if ($write_to_file)
								@fwrite($file_handle, $data);
						}
						Db_DbHelper::free_result($om_query_resource);
					}
				}
				
				if ($include_images)
				{
					self::export_images_to_zip(PATH_APP.'/temp/images.zip', $images_to_export, $base_image_export_path);
					self::final_archive(PATH_APP.'/temp/', 'export.zip', array('images.zip', 'export.csv'));
					
					self::write_download_headers('application/zip', 'products.zip');
					readfile(PATH_APP.'/temp/export.zip');
					
					foreach(array('images.zip', 'export.csv', 'export.zip') as $f)
						@unlink(PATH_APP.'/temp/'. $f);
				}
				
				if ($file_handle)
					@fclose($file_handle);
			} catch (exception $ex)
			{
				if ($file_handle)
					@fclose($file_handle);
				
				throw $ex;
			}
		}
		
		protected static function get_options($product)
		{
			$options = Db_DbHelper::objectArray('select * from shop_custom_attributes where product_id=:product_id',
				array('product_id'=>$product->id)
			);
			
			$result = array();
			foreach ($options as $option)
			{
				$values = str_replace("\n", "|", $option->attribute_values);
				$option_str = $option->name.': '.$values;
				$result[] = $option_str;
			}
			
			if ($product->has_grouped_products)
			{
				$grouped_options = Db_DbHelper::scalarArray('SELECT grouped_option_desc FROM shop_products WHERE id=:product_id',
					array('product_id'=>$product->id)
				);
			
				if (count($grouped_options))
					$result[] = $product->grouped_attribute_name.': '.join('|', $grouped_options);
			}
			
			return implode("\n", $result);
		}
		
		protected static function format_om_row($product_sku, $om_record, &$row_data, $columns, $om_columns)
		{
			$om_record->fill_external($row_data);
		
			$row = array();

			foreach ($columns as $column)
			{
				if ($column->dbName == 'product_variant_flag')
					$row[$column->dbName] = 1;
				elseif ($column->dbName == 'csv_import_parent_sku')
					$row[$column->dbName] = $product_sku;
				elseif ($column->dbName == 'options')
					$row[$column->dbName] = self::get_om_options($om_record);
				elseif ($column->dbName == 'price_tiers')
					$row[$column->dbName] = self::get_om_price_tiers($om_record);
				elseif ($column->dbName == 'price')
					$row[$column->dbName] = $om_record->base_price;
				elseif ($column->dbName == 'enabled')
					$row[$column->dbName] = $om_record->disabled ? '' : '1';
				elseif ($column->dbName == 'images' && isset($row_data['images']))
					$row[$column->dbName] = $row_data['images'];
				else
				{
					$db_name = $column->dbName;
					$value = $om_record->$db_name;
					if (is_object($value))
					{
						if ($value instanceof Phpr_DateTime)
							$value = $value->toSqlDate();
						else
							$value = null;
					}
					
					$row[$column->dbName] = $value;
				}
			}
			
			return $row;
		}
		
		protected static function format_product_row($product, &$row_data, $columns, $sku_index)
		{
			$product->fill_external($row_data);

			$row = array();
		
			foreach ($columns as $column)
			{
				if ($column->dbName == 'categories')
					$row[$column->dbName] = self::list_categories($product);
				elseif ($column->dbName == 'manufacturer_link')
					$row[$column->dbName] = self::get_manufacturer($product);
				elseif ($column->dbName == 'tax_class')
					$row[$column->dbName] = self::get_tax_class($product);
				elseif ($column->dbName == 'csv_import_parent_sku')
					$row[$column->dbName] = self::get_parent_sku($product, $sku_index) ? (self::get_parent_sku($product, $sku_index).'-master') : '';
				elseif ($column->dbName == 'options')
				{
					$row[$column->dbName] = self::get_options($product);
					// This is a bit of a hack for grouped products
					if ($product->product_variant_flag)
					{
						$options = explode("\n", $row[$column->dbName]);
						$row[$column->dbName] = join("\n", 
							array_filter(array_merge($options, array("{$row_data['parent_grouped_attribute_name']}: {$row_data['grouped_option_desc']}")))
						);
					}
				} elseif ($column->dbName == 'product_extra_options')
					$row[$column->dbName] = self::get_extra_options($product);
				elseif ($column->dbName == 'perproduct_shipping_cost')
					$row[$column->dbName] = self::get_perproduct_shipping_cost($product);
				elseif ($column->dbName == 'csv_related_sku')
					$row[$column->dbName] = self::get_related_skus($product);
				elseif ($column->dbName == 'extra_option_sets')
					$row[$column->dbName] = self::get_global_extra_sets($product);
				elseif (preg_match('/^ATTR:/', $column->dbName))
					$row[$column->dbName] = self::get_attribute_value($product, $column->dbName);
				elseif (preg_match('/^PROP:/', $column->dbName))
					$row[$column->dbName] = self::get_property_value($product, $column->dbName);
				elseif ($column->dbName == 'product_groups')
					$row[$column->dbName] = self::list_product_groups($product);
				else if ($column->dbName == 'price_tiers')
					$row[$column->dbName] = self::get_price_tiers($product);
				elseif ($column->dbName == 'product_type')
					$row[$column->dbName] = self::get_product_type($product);
				elseif ($column->dbName == 'images' && isset($row_data['images']))
					$row[$column->dbName] = $row_data['images'];
				else
				{
					$db_name = $column->dbName;

					$value = $product->$db_name;
					if (is_object($value))
					{
						if ($value instanceof Phpr_DateTime)
							$value = $value->toSqlDate();
						else
							$value = null;
					}
				
					$row[$column->dbName] = $value;
				}
			}
			
			return $row;
		}
	}

?>
