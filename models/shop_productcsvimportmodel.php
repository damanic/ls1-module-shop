<?

	$products_import_csv_images_dir = null;

	class Shop_ProductCsvImportModel extends Backend_CsvImportModel
	{
		public $table_name = 'shop_products';

		public $has_many = array(
			'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_ProductCsvImportModel'", 'order'=>'id', 'delete'=>true),
			'config_import'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_ProductCsvImportModel'", 'order'=>'id', 'delete'=>true),
			'images_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_ProductCsvImportModel'", 'order'=>'id', 'delete'=>true),
		);
		
		public $has_and_belongs_to_many = array(
			'categories'=>array('class_name'=>'Shop_Category', 'join_table'=>'shop_products_categories', 'order'=>'name'),
			'product_groups'=>array('class_name'=>'Shop_CustomGroup', 'join_table'=>'shop_products_customgroups', 'order'=>'name'),
			'price_tiers'=>array('class_name'=>'Shop_PriceTier', 'join_table'=>'shop_tier_prices', 'order'=>'id')
		);

		public $belongs_to = array(
			'tax_class'=>array('class_name'=>'Shop_TaxClass', 'foreign_key'=>'tax_class_id'),
			'manufacturer'=>array('class_name'=>'Shop_Manufacturer', 'foreign_key'=>'manufacturer_id'),
			'product_type'=>array('class_name'=>'Shop_ProductType', 'foreign_key'=>'product_type_id')
		);

		public $custom_columns = array(
			'auto_product_types'=>db_bool,
			'auto_create_categories'=>db_bool,
			'import_product_images'=>db_bool,
			'images_path'=>db_text,
			'images_directory_path'=>db_text,
			'auto_tax_classes'=>db_bool,
			'auto_manufacturers'=>db_bool,
			'update_existing_sku'=>db_bool,
			'descriptions_html'=>db_bool,
			'short_descriptions_html'=>db_bool,
			'import_product_files'=>db_bool,
			'files_directory_path'=>db_text,
			'auto_create_product_groups'=>db_bool
		);
		
		public $auto_create_categories = true;
		public $auto_tax_classes = true;
		public $auto_manufacturers = true;
		public $auto_create_product_groups = true;
		public $auto_product_types = false;
		
		protected $existing_cagetories = null;
		protected $existing_manufacturers = null;
		protected $existing_tax_classes = null;
		protected $existing_product_groups = null;
		protected $existing_customer_groups = null;
		protected $existing_product_types = null;

		protected $sku_cache = null;
		protected $extra_set_cache = null;
		
		protected $current_time = null;
		
		protected $image_archive_dirs = array();
		protected $images_root_dir = null;
		protected $images_server_dir = null;
		protected $import_images = false;
		protected $files_server_dir_path = null;
		
		protected $option_matrix_manager = null;
		protected $om_parent_sku_column_index = null;
		protected $om_options_column_index = null;

		protected $product_columns = array();

		public function __construct($values = null, $options = array())
		{
			parent::__construct($values, $options);
			
			$this->tax_class_id = Shop_TaxClass::get_default_class_id();
		}

		public function define_columns($context = null)
		{
			parent::define_columns($context);

			$this->define_column('auto_product_types', 'I want LemonStand to apply product types specified in the CSV file');
			$this->define_relation_column('product_type', 'product_type', 'Product Type ', db_varchar, '@name')->defaultInvisible()->listTitle('Type')->validation()->required();

			$this->define_column('auto_create_categories', 'I want LemonStand to create categories specified in the CSV file');
			$this->define_multi_relation_column('categories', 'categories', 'Categories', '@name')->defaultInvisible()->validation();
			
			$this->define_column('import_product_images', 'I have a ZIP archive(s) or a directory on the server containing product images and I want LemonStand to import them');
			$this->define_multi_relation_column('images_file', 'images_file', 'Images', '@name')->invisible();
			$this->define_column('images_path', 'Path to the images directory in the ZIP file or in the images directory');
			$this->define_column('images_directory_path', 'Images directory on the server');

			$this->define_column('import_product_files', 'I have a directory on the server containing downloadable product files and I want LemonStand to import them');
			$this->define_column('files_directory_path', 'Files directory on the server');
			
			$this->define_column('auto_tax_classes', 'I want LemonStand to create tax classes specified in the CSV file');
			$this->define_relation_column('tax_class', 'tax_class', 'Tax Class ', db_varchar, '@name');
			
			$this->define_column('auto_manufacturers', 'I want LemonStand to create manufacturers specified in the CSV file');
			$this->define_relation_column('manufacturer', 'manufacturer', 'Manufacturer ', db_varchar, '@name');

			$this->define_column('update_existing_sku', 'I want LemonStand to update existing products');
			$this->define_column('descriptions_html', 'The Long Description field in the CSV file contains HTML tags');
			$this->define_column('short_descriptions_html', 'The Short Description field in the CSV file contains HTML tags');
			
			$this->define_column('auto_create_product_groups', 'I want LemonStand to create product groups specified in the CSV file');
			$this->define_multi_relation_column('product_groups', 'product_groups', 'Product Groups', '@name')->defaultInvisible()->validation();
		}

		public function define_form_fields($context = null)
		{
			parent::define_form_fields($context);

			$this->add_form_field('auto_product_types')->comment('You need to match the LemonStand product <strong>Product Type</strong> field to a CSV file column in order to use this feature. Otherwise please select a product type for all imported products in the list below. This field is required for new products.', 'above', true);
			$this->add_form_field('product_type')->comment('Please select a product type for imported products', 'above')->emptyOption('<please select>')->cssClassName('expandable');

			$this->add_form_field('auto_create_categories')->comment('You need to match the LemonStand product <strong>Categories</strong> field to a CSV file column in order to use this feature. Otherwise please select categories for all imported products in the list below. This field is required for new products.', 'above', true);
			$this->add_form_field('categories')->comment('Please select the categories that the imported products will belong to', 'above')->referenceSort('name')->cssClassName('expandable');

			$this->add_form_field('images_file', 'left')->renderAs(frm_file_attachments)->addDocumentLabel('Upload image archive(s)')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noAttachmentsLabel('');
			$this->add_form_field('import_product_images')->comment('You need to match the LemonStand product <strong>Images</strong> field to a CSV file column in order to use this feature. The images field in the CSV file should contain paths to image files in the images archive. You can upload multiple image ZIP archives. We recommend to split large image sets to smaller archives, containing not more than 20,000 files each. Size of an individual file should not exceed '.Phpr_Files::fileSize(Phpr_Files::maxUploadSize()).'.<br/><br/>Note: you can use image ZIP archives or the images directory, or both.', 'above', true);
			$this->add_form_field('images_directory_path')->comment('Use this field to specify a directory on the server, which contains product images. Please use an absolute path, for example /users/me/import_images. The absolute path of your LemonStand directory is '.PATH_APP, 'above', true)->renderAs(frm_text)->cssClassName('offsetTop');
			$this->add_form_field('images_path')->comment('Use this field to specify a directory inside the ZIP archive or inside the images directory that the images column in the CSV file refers to. For example, if the images column refers to images/product.jpeg file, and the actual path to the product.jpeg file in the ZIP archive is products/images/product.jpeg, you should specify the <strong>products</strong> value in this field. Leave this field empty if the images directory is in the root of the archive.', 'above', true)->renderAs(frm_text)->cssClassName('offsetTop expandable');

			$this->add_form_field('import_product_files')->comment('You need to match the LemonStand product <strong>Files</strong> field to a CSV file column in order to use this feature. The files field in the CSV file should contain file names separated with comma.', 'above', true);
			$this->add_form_field('files_directory_path')->comment('Use this field to specify a directory on the server, which contains product files. Please use an absolute path, for example /users/me/import_files. The absolute path of your LemonStand directory is '.PATH_APP, 'above', true)->renderAs(frm_text)->cssClassName('expandable');

			$this->add_form_field('auto_tax_classes')->comment('You need to match the LemonStand product <strong>Tax Class</strong> field to a CSV file column in order to use this feature. Otherwise please select a tax class for all imported products in the field below. This field is required for new products.', 'above', true);
			$this->add_form_field('tax_class')->comment('Please select a tax class for imported products', 'above')->cssClassName('expandable');

			$this->add_form_field('auto_manufacturers')->comment('You need to match the LemonStand product <strong>Manufacturer</strong> field to a CSV file column in order to use this feature. Otherwise please select a manufacturer for all imported products in the field below. This field is optional.', 'above', true);
			$this->add_form_field('manufacturer')->comment('Please select a manufacturer for imported products', 'above')->emptyOption('<please select>')->cssClassName('expandable');

			$this->add_form_field('update_existing_sku')->comment('If you leave this checkbox unchecked, LemonStand will skip products with existing SKU values (Option Matrix products are identified by option values). Otherwise existing products will be updated using information from the CSV file and other parameters specified on this page.', 'above', true);
			$this->add_form_field('descriptions_html')->comment('If you leave this checkbox unchecked, LemonStand will treat data in the Long Description field as plain text.', 'above', true);
			$this->add_form_field('short_descriptions_html')->comment('If you leave this checkbox unchecked, LemonStand will treat data in the Short Description field as plain text.', 'above', true);
			
			$this->add_form_field('auto_create_product_groups')->comment('You need to match the LemonStand product <strong>Product Groups</strong> field to a CSV file column in order to use this feature. Otherwise please select product groups for all imported products in the list below if you would like to add the imported products to a product group.', 'above', true);	
			$this->add_form_field('product_groups')->comment('Please select the product groups that the imported products will belong to or leave none selected to import with no product groups.', 'above')->referenceSort('name')->cssClassName('expandable');
		}
		
		public function import_csv_data($data_model, $session_key, $column_map, $import_manager, $delimeter, $first_row_titles)
		{
			global $products_import_csv_images_dir;

			@set_time_limit(3600);
			$config_object = $this;
			
			$import_option_matrix_records = false;
			$om_flag_column_index = null;

			/*
			 * Validate import configuration
			 */
			
			if(!$config_object->update_existing_sku)
			{
				if (!$import_manager->csvImportDbColumnPresented($column_map, 'name'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Name field or select the "I want LemonStand to update products with existing SKU" checkbox if updating products.');
				if (!$import_manager->csvImportDbColumnPresented($column_map, 'price'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Price field or select the "I want LemonStand to update products with existing SKU" checkbox if updating products.');
				
				if (!$config_object->auto_product_types && !$config_object->product_type)
					throw new Phpr_ApplicationException('Please select a Product type or select the "I want LemonStand to update products with existing SKU" checkbox if updating products.');
				elseif ($config_object->auto_product_types && !$import_manager->csvImportDbColumnPresented($column_map, 'product_type'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Product type field, or uncheck the "I want LemonStand to apply product types specified in the CSV file" checkbox.');
				
				if ($config_object->auto_tax_classes && !$import_manager->csvImportDbColumnPresented($column_map, 'tax_class'))
				{
					//checkbox is selected and tax class column is not specified
					throw new Phpr_ApplicationException('If importing new products, please specify a matching column for the Tax Class product field or uncheck the "I want LemonStand to create tax classes specified in the CSV file" checkbox. 
						If updating products, please select the "I want LemonStand to update products with existing SKU" checkbox.');
				}
				if (!$config_object->auto_create_categories)
				{
					if (!$config_object->categories || ($config_object->categories instanceof Db_DataCollection && !$config_object->categories->count))
						throw new Phpr_ApplicationException('Please select product categories.');
				}
			}

			if (!$config_object->auto_tax_classes && !$config_object->tax_class)
				throw new Phpr_ApplicationException('Please select a tax class.');

			if ($config_object->auto_manufacturers)
			{
				if (!$import_manager->csvImportDbColumnPresented($column_map, 'manufacturer_link'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Manufacturer product field, or uncheck the "I want LemonStand to create manufacturers specified in the CSV file" checkbox.');
			}
			
			if ($config_object->auto_create_product_groups)
			{
				if (!$import_manager->csvImportDbColumnPresented($column_map, 'product_groups'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Product Groups product field, or uncheck the "I want LemonStand to create product groups specified in the CSV file" checkbox.');
			}
			
			foreach ($column_map as $column_index=>$db_names)
			{
				if (in_array('csv_import_om_flag', $db_names))
				{
					$import_option_matrix_records = true;
					$om_flag_column_index = $column_index;
				} elseif (in_array('csv_import_om_parent_sku', $db_names))
					$this->om_parent_sku_column_index = $column_index;
				elseif (in_array('options', $db_names))
					$this->om_options_column_index = $column_index;
			}
			
			if ($import_option_matrix_records && $this->om_parent_sku_column_index === null)
				throw new Phpr_ApplicationException('Please specify a matching column for Option Matrix - Parent Product SKU product field or remove Option Matrix Record Flag field from the column list.');
			
			if ($import_option_matrix_records && $this->om_options_column_index === null)
				throw new Phpr_ApplicationException('Please specify a matching column for Options product field or remove Option Matrix Record Flag field from the column list.');
				
			if ($import_option_matrix_records)
				$this->option_matrix_manager = new Shop_OptionMatrixManager();
				
			$test_product = Shop_Product::create();
			$this->product_columns = array_keys($test_product->fields());

			try
			{
				$images_archive_dirs = array();
				$images_root_dir = null;
				$csv_handler = null;

				/*
				 * Validate images archive and unzip the archive
				 */

				if ($config_object->import_product_images)
				{
					$images_server_dir = trim($config_object->images_directory_path);
					
					$this->import_images = $config_object->import_product_images;
					$image_files = $config_object->list_related_records_deferred('images_file', $session_key);
					if (!$image_files->count && !strlen($images_server_dir))
						throw new Phpr_ApplicationException('Please upload an image archive or specify the images directory on the server, or uncheck the option "I have a ZIP archive(s) or a directory on the server containing product images and I want LemonStand to import them".');

					foreach ($image_files as $image_archive_file)
					{
						$images_archive_dir = PATH_APP.'/temp/'.uniqid('pi_');
						$images_archive_dirs[] = $images_archive_dir;

						if (!@mkdir($images_archive_dir))
							throw new Phpr_ApplicationException('Unable to create a temporary directory');

						Core_ZipHelper::initZip();
						$archivePath = PATH_APP.$image_archive_file->getPath();

						$products_import_csv_images_dir = $images_archive_dir;
						$archive = new PclZip($archivePath);

						if (!@$archive->extract(PCLZIP_OPT_PATH, $images_archive_dir, PCLZIP_CB_PRE_EXTRACT, 'shop_product_import_img_extract'))
							throw new Phpr_ApplicationException('Unable to extract the uploaded images archive');
					}

					if (strlen($images_server_dir))
					{
						if (!file_exists($images_server_dir))
							throw new Phpr_ApplicationException('Images directory not found: '.$images_server_dir);
						
						$images_archive_dirs[] = $images_server_dir;
						$this->images_server_dir = $images_server_dir;
					}

					$images_root_dir = trim($config_object->images_path);
					if (strlen($images_root_dir))
					{
						$images_root_dir = str_replace('\\', '/', $images_root_dir);
						if (substr($images_root_dir, 0, 1) == '/')
							$images_root_dir = substr($images_root_dir, 1);

						if (substr($images_root_dir, -1) == '/')
							$images_root_dir = substr($images_root_dir, 0, -1);

						if (strpos($images_root_dir, '..') !== false)
							throw new Phpr_ApplicationException('Invalid value specified in the "Path to the images directory in the ZIP file" field. The .. symbol is not allowed.');
							
						$images_root_dir = mb_strtoupper($images_root_dir);
					}
					
					$this->image_archive_dirs = $images_archive_dirs;
					$this->images_root_dir = $images_root_dir;
				}
				
				/*
				 * Validate files path
				 */
				
				if ($config_object->import_product_files)
				{
					$files_server_dir = trim($config_object->files_directory_path);
					if (!strlen($files_server_dir))
						throw new Phpr_ApplicationException('Please specify the files directory on the server, or uncheck the option "I have a directory on the server containing downloadable product files and I want LemonStand to import them".');
						
					if (!file_exists($files_server_dir))
						throw new Phpr_ApplicationException('Product files directory not found: '.$files_server_dir);

					$this->files_server_dir_path = $files_server_dir;
				}

				/*
				 * Import products
				 */

				$added = 0;
				$skipped = 0;
				$skipped_rows = array();
				$updated = 0;
				$errors = array();
				$warnings = array();
				
				$grouped_parent_sku = array();
				$related_sku = array();

				$csv_handle = $import_manager->csvImportGetCsvFileHandle();
				$column_definitions = $data_model->get_csv_import_columns();

				$first_row_found = false;
				$line_number = 0;
				
				$csv_update_related_products = false;
				
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
						$existing_product_id = null;
						$product_categories = array();
						$product_images = array();
						$product_files = array();
						$product_attributes = array();
						$product_otpions = null;
						$product_extra_otpions = null;
						$product_global_extra_sets = null;
						
						$update_options = false;
						$update_extra_options = false;
						$update_extra_set_links = false;
						$update_product_images = false;
						$update_product_files = false;
						
						/*
						 * If it is Option Matrix record, call the import_om_record() method
						 * and skip the row.
						 */

						if ($import_option_matrix_records && 
							isset($row[$om_flag_column_index]) && 
							Core_CsvHelper::boolean($row[$om_flag_column_index]))
						{

							$import_result = $this->import_om_record($column_map, $row, $warnings, $line_number);
							if ($import_result->status != Shop_OptionMatrixManager::status_skipped)
							{
								if ($import_result->operation == Shop_OptionMatrixManager::operation_add)
									$added++;
								else
									$updated++;
							} else {
								$skipped++;
								$skipped_rows[$line_number] = 'Existing Option Matrix product.';
							}
								
							foreach ($import_result->warnings as $warning)
								$this->add_warnings_line($warnings, $line_number, $warning);

							continue;
						}

						/*
						 * Import product
						 */
					
						if (!$config_object->auto_create_categories)
						{
							$product_categories = $config_object->categories;
							$bind['categories'] = $product_categories[0];
						}
						
						if (!$config_object->auto_manufacturers && $config_object->manufacturer)
							$bind['manufacturer_id'] = $config_object->manufacturer->id;
						
						if (!$config_object->auto_tax_classes)
							$bind['tax_class_id'] = $config_object->tax_class->id;
							
						if (!$config_object->auto_create_product_groups)
						{
							if(count($config_object->product_groups))
								$product_groups = $config_object->product_groups;
							else $product_groups = array();
						}
						
						if($config_object->product_type)
							$bind['product_type_id'] = $config_object->product_type->id;

						foreach ($column_map as $column_index=>$db_names)
						{
							if (!array_key_exists($column_index, $row))
								continue;
							
							$column_value = trim($row[$column_index]);
							foreach ($db_names as $db_name)
							{
								/*
								 * Skip unknown columns
								 */

								if (!array_key_exists($db_name, $column_definitions))
									continue;

								/*
								 * Find or update products with existing SKU
								 */

								if ($db_name == 'sku' && strlen($column_value))
								{
									$existing_product_id = Db_DbHelper::scalar('select id from shop_products where lower(sku)=:sku and not (exists(select * from db_deferred_bindings where detail_class_name=\'Shop_Product\' and master_relation_name=\'grouped_products_all\' and detail_key_value=shop_products.id))', array('sku'=>mb_strtolower($column_value)));
									if ($existing_product_id)
									{
										if (!$config_object->update_existing_sku)
										{
											/*
											 * Go to next row
											 */

											$skipped++;
											$skipped_rows[$line_number] = 'Existing SKU: '.$column_value;
											continue 3;
										}
									}
								}

								/*
								 * Prepare product fields
								 */

								if ($column_definitions[$db_name]->type == db_bool)
								{
									$bind[$db_name] = Core_CsvHelper::boolean($column_value) ? '1' : '0';
								} else
								{
									if ($column_definitions[$db_name]->type == db_float)
										$column_value = $import_manager->csvImportFloatValue($column_value);

									if ($column_definitions[$db_name]->type == db_number)
										$column_value = $import_manager->csvImportNumericValue($column_value);

									if ($db_name == 'short_description')
									{
										if (!$config_object->short_descriptions_html)
											$column_value = html_entity_decode(strip_tags($column_value));
									}

									if ($db_name == 'description')
									{
										if (!$config_object->descriptions_html)
											$column_value = Phpr_Html::paragraphize($column_value);
											
										$bind['pt_description'] = html_entity_decode(strip_tags($column_value), ENT_QUOTES, 'UTF-8');
									}
									
									if ($db_name == 'product_type')
									{
										if ($config_object->auto_product_types)
										{
											if(strlen($column_value))
												$bind['product_type_id'] = $this->get_product_type_id($column_value);
										}

										continue;
									}

									if ($db_name == 'categories')
									{
										if ($config_object->auto_create_categories)
										{
											$bind['categories'] = $column_value;
											$product_categories = $this->create_categories($column_value, $existing_product_id);
										}

										continue;
									}

									if ($db_name == 'manufacturer_link')
									{
										$db_name = 'manufacturer_id';
									
										if ($config_object->auto_manufacturers)
											$column_value = $this->create_manufacturer($column_value);
										else
											continue;
									}

									if ($db_name == 'csv_related_sku')
										$csv_update_related_products = true;

									if ($db_name == 'tax_class')
									{
										$db_name = 'tax_class_id';

										if ($config_object->auto_tax_classes && strlen($column_value))
											$column_value = $this->create_tax_class($column_value);
										else
											continue;
									}

									if (preg_match('/^ATTR:/', $db_name))
									{
										$product_attributes[$db_name] = $column_value;
										continue;
									}
									
									if ($db_name == 'options')
									{
										$product_otpions = $column_value;
										$update_options = true;
										continue;
									}
									
									if ($db_name == 'product_extra_options')
									{
										$product_extra_otpions = $column_value;
										$update_extra_options = true;
										continue;
									}
									
									if ($db_name == 'extra_option_sets')
									{
										$product_global_extra_sets = $column_value;
										$update_extra_set_links = true;
										continue;
									}

									if ($db_name == 'images')
									{
										$update_product_images = true;

										if (!$config_object->import_product_images)
											continue;

										$product_images = $this->prepare_images(
											$this->extract_product_images($column_value), 
											$images_archive_dirs, 
											$images_root_dir,
											$warnings,
											$line_number);

										continue;
									}
									
									if ($db_name == 'files')
									{
										$update_product_files = true;

										if (!$config_object->import_product_files)
											continue;

										$product_files = $this->prepare_files(
											$this->extract_product_files($column_value));

										continue;
									}

									if ($db_name == 'product_groups')
									{
										if ($config_object->auto_create_product_groups) {
											$product_groups = $this->create_product_groups($column_value);
										}
										continue;
									}

									$bind[$db_name] = $column_value;
								}
							}
						}

						$this->validate_fields($bind, $existing_product_id, $warnings, $line_number);
						if(array_key_exists('categories', $bind))
							unset($bind['categories']);

						/*
						 * Create or update a product record
						 */

						$product_id = null;
						if ($existing_product_id)
						{
							if ($update_options)
								$this->check_deleted_options($existing_product_id, $product_otpions);
							
							$product_id = $this->update_product_fields($existing_product_id, $bind);
							$updated++;
						}
						else
						{
							$product_id = $this->create_product($bind);
							$added++;
						}
						
						if (array_key_exists('csv_import_parent_sku', $bind))
						{
							$value = trim(mb_strtoupper($bind['csv_import_parent_sku']));
							if(array_key_exists('sku', $bind))
								$product_sku = $bind['sku'];
							else $product_sku = null;
							$grouped_parent_sku[$product_id] = array($value, $line_number, $product_sku, trim($bind['csv_import_parent_sku']));
						}

						if (array_key_exists('csv_related_sku', $bind))
						{
							$value = trim(mb_strtoupper($bind['csv_related_sku']));
							$related_sku[$product_id] = array($value, $line_number);
						} else
							$related_sku[$product_id] = array('', $line_number);

						/*
						 * Set data relations
						 */

						if ($update_options)
							$this->set_product_options($product_id, $product_otpions);
						
						if(count($product_categories))
							$this->set_product_categories($product_id, $product_categories);

						$this->set_product_attributes($product_id, $product_attributes);
						
						if ($config_object->import_product_images && $update_product_images)
							$this->set_product_images($product_id, $product_images);

						if ($config_object->import_product_files && $update_product_files)
							$this->set_product_files($product_id, $product_files);
							
						if ($update_extra_options)
							$this->set_product_extra_options($product_id, $product_extra_otpions);
							
						if ($update_extra_set_links)
							$this->set_product_extra_set_links($product_id, $product_global_extra_sets);
						
						$this->set_product_groups($product_id, $product_groups);
						
						if(array_key_exists('tier_price_compiled', $bind))
							$this->set_price_tiers($product_id, $bind['tier_price_compiled']);
							
						if ($existing_product_id)
							Backend::$events->fireEvent('shop:onAfterCsvProductUpdated', $bind, $product_id, $row);
						else
							Backend::$events->fireEvent('shop:onAfterCsvProductCreated', $bind, $product_id, $row);

					} catch (Exception $ex)
					{
						$errors[$line_number] = $ex->getMessage();
					}
				}
				
				/*
				 * Update grouped product relations
				 */
				
				if (count($grouped_parent_sku))
				{
					foreach ($grouped_parent_sku as $relation_product=>$relation_info)
					{
						$line_number = $relation_info[1];
						try
						{
							$this->update_parent_id($relation_product, $relation_info[0], $warnings, $line_number, $relation_info[2], $relation_info[3]);
						}
						catch (Exception $ex)
						{
							$errors[$line_number] = $ex->getMessage();
						}
					}
				}

				/*
				 * Update related products relations
				 */

				if (count($related_sku) && $csv_update_related_products)
				{
					foreach ($related_sku as $relation_product=>$relation_info)
					{
						$line_number = $relation_info[1];
						try
						{
							$this->update_related_products($relation_product, $relation_info[0], $warnings, $line_number);
						}
						catch (Exception $ex)
						{
							$errors[$line_number] = $ex->getMessage();
						}
					}
				}

				/*
				 * Delete images archive
				 */

				foreach ($images_archive_dirs as $dir)
				{
					if (strlen($dir) && $dir != $this->images_server_dir)
						@Phpr_Files::removeDirRecursive($dir);
				}
					
				/*
				 * Return result object
				 */
				
				$result = array(
					'added'=>$added,
					'skipped'=>$skipped,
					'skipped_rows'=>$skipped_rows,
					'updated'=>$updated,
					'errors'=>$errors,
					'warnings'=>$warnings
				);

				return (object)$result;
			} catch (Exception $ex)
			{
				foreach ($images_archive_dirs as $dir)
				{
					if (strlen($dir) && $dir != $this->images_server_dir)
						@Phpr_Files::removeDirRecursive($dir);
				}

				if ($csv_handler)
					@fclose($csv_handler);
				
				throw $ex;
			}
		}
		
		protected function find_category_parents($category, &$category_cache, &$parents)
		{
			$parent_key = $category->category_id ? $category->category_id : -1;
			
			if (array_key_exists($parent_key, $category_cache))
			{
				$parent = $category_cache[$parent_key];
				$parents[] = $parent;
				$this->find_category_parents($parent, $category_cache, $parents);
			}
		}
		
		protected function get_product_type_id($value)
		{
			if (!$this->existing_product_types)
			{
				$this->existing_product_types = array();
				$existing_product_types = Db_DbHelper::objectArray('select id, name from shop_product_types');
				if (count($existing_product_types))
				{
					foreach ($existing_product_types as $type)
					{
						$this->existing_product_types[trim(mb_strtolower($type->name))] = $type->id;
					}
				}
			}
			if($value != '')
			{
				if ($this->existing_product_types && array_key_exists(mb_strtolower($value), $this->existing_product_types))
					return $this->existing_product_types[mb_strtolower($value)];
				else throw new Phpr_ApplicationException('Specified Product type does not exist ('.$value.').');
			}
		}
		
		protected function create_product_groups($value)
		{
			$result = array();
			if (!$this->existing_product_groups)
			{
				$this->existing_product_groups = array();
				$existing_product_groups = Db_DbHelper::objectArray('select id, name from shop_custom_group');
				if (count($existing_product_groups))
				{
					foreach ($existing_product_groups as $group)
					{
						$this->existing_product_groups[trim(mb_strtolower($group->name))] = $group->id;
					}
				}
			}
			$product_groups = explode('|', $value);
			if ($value!= '' && count($product_groups))
			{
				foreach ($product_groups as $group)
				{
					$group = trim($group);
					if(!strlen($group))
						continue;
					$key = mb_strtolower($group);
						
					if ($this->existing_product_groups && array_key_exists($key, $this->existing_product_groups))
						$result[] = $this->existing_product_groups[$key];
					else
					{
						//product group does not exist, create it!
						$product_group_object = $this->create_product_group($group);
						
						$result[] = $this->existing_product_groups[$key];
					}
				}
			}

			return $result;
		}
		
		protected function create_product_group($product_group_name)
		{
			$key = mb_strtolower($product_group_name);
			$existing_product_group = null;
			
			$product_group_exists = array_key_exists($key, $this->existing_product_groups);
			if ($product_group_exists)
			{
				$existing_product_group = $this->existing_product_groups[$key];
			}
			else
			{
				$product_group_obj = new Shop_CustomGroup();
				$product_group_obj->name = $product_group_name;
				$product_group_obj->code = $this->prepare_product_group_code($product_group_name);
				$product_group_obj->save();
				
				$this->existing_product_groups[$key] = $product_group_obj->id;
				return $product_group_obj;
			}		
		}
		
		protected function prepare_product_group_code($group_name)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			$code = $this->format_url_name($group_name);
			
			$orig_code = $code;
			$counter = 1;
			while (Db_DbHelper::scalar('select count(*) from shop_custom_group where code=:code', array('code'=>$code)))
			{
				$code = $orig_code.$separator.$counter;
				$counter++;
			}
			
			return $code;
		}

		protected function create_categories($value, $existing_product = false)
		{
			$result = array();

			$updated_values = Backend::$events->fireEvent('shop:onOverrideProductCategoryCsvImportData', $value);
			foreach ($updated_values as $updated_value)
			{
				if ($updated_value !== false)
					$value = $updated_value;
			}
			
			if (!$this->existing_cagetories)
			{
				$existing_categories = Db_DbHelper::objectArray('select id, name, category_id from shop_categories');
				$this->existing_cagetories = array();
				
				$category_list = array();
				foreach ($existing_categories as $category)
					$category_list[$category->id] = $category;
				
				foreach ($category_list as $category)
				{
					$parents = array($category);
					$this->find_category_parents($category, $category_list, $parents);
					$parents = array_reverse($parents);
					
					$category_path = array();
					foreach ($parents as $parent)
						$category_path[] = $parent->name;
						
					$category_path = implode('=>', $category_path);
					$this->existing_cagetories[trim(mb_strtolower($category_path))] = $category->id;
				}
			}

			$categories = explode('|', $value);
			foreach ($categories as $category)
			{
				$category = trim($category);
				
				if (!strlen($category))
					continue;
				
				$category_path = explode('=>', $category);
				$category_path_fixed = array();
				foreach ($category_path as $path_element)
					$category_path_fixed[] = trim($path_element);
					
				$category_path = implode('=>', $category_path_fixed);
				
				$key = mb_strtolower($category_path);
				
				if (array_key_exists($key, $this->existing_cagetories))
					$result[] = $this->existing_cagetories[$key];
				else
				{
					$category_obj = $this->create_category_recursively($category_path);

					$this->existing_cagetories[$key] = $category_obj->id;
					$result[] = $this->existing_cagetories[$key];
				}
			}
			
			if (!$result && !$existing_product)
				throw new Phpr_ApplicationException('Category name is not specified for a new product.');

			return $result;
		}
		
		protected function create_category_recursively($category_path)
		{
			$path = $category_path;
			$key = mb_strtolower($category_path);
			$key_parts = explode('=>', $key);
			$name_parts = explode('=>', $category_path);
			$parent_category = null;
			$categories_to_create = array();
			
			$existing_category = null;
			while (strlen($key))
			{
				$category_exists = array_key_exists($key, $this->existing_cagetories);
				if ($category_exists)
				{
					$existing_category = $this->existing_cagetories[$key];
					break;
				}

				array_pop($key_parts);
				$key = implode('=>', $key_parts);
				$categories_to_create[] = array_pop($name_parts);
			}

			$categories_to_create = array_reverse($categories_to_create);

			$parent_id = $existing_category;
			$parent_url_name = $key;
			
			$created_category = null;
			foreach ($categories_to_create as $category_name)
			{
				$parent_url_name .= strlen($parent_url_name) ? '=>'.$category_name : $category_name;

				$category_obj = new Shop_Category();
				$category_obj->name = $category_name;
				$category_obj->category_id = $parent_id;
				$category_obj->url_name = $this->prepare_category_url_name($category_name, $parent_id);
				$category_obj->save();

				$this->existing_cagetories[mb_strtolower($parent_url_name)] = $category_obj->id;

				$parent_id = $category_obj->id;
				$created_category = $category_obj;
			}
			
			return $created_category;
		}
		
		protected function create_manufacturer($value)
		{
			if (!$this->existing_manufacturers)
			{
				$existing_manufacturers = Db_DbHelper::objectArray('select id, name from shop_manufacturers');
				$this->existing_manufacturers = array();
				foreach ($existing_manufacturers as $manufacturer)
					$this->existing_manufacturers[trim(mb_strtolower($manufacturer->name))] = $manufacturer->id;
			}

			$manufacturer_name = trim($value);
			
			if (!strlen($manufacturer_name))
				return null;
			
			$key = mb_strtolower($manufacturer_name);
			if (array_key_exists($key, $this->existing_manufacturers))
				return $this->existing_manufacturers[$key];

			$manufacturer = new Shop_Manufacturer();
			$manufacturer->name = $manufacturer_name;
			$manufacturer->url_name = $this->prepare_manufacturer_url_name($manufacturer->name);
			$manufacturer->save();

			$this->existing_manufacturers[$key] = $manufacturer->id;
			return $this->existing_manufacturers[$key];
		}
		
		protected function create_tax_class($value)
		{
			if (!$this->existing_tax_classes)
			{
				$existing_tax_classes = Db_DbHelper::objectArray('select id, name from shop_tax_classes');
				$this->existing_tax_classes = array();
				foreach ($existing_tax_classes as $tax_class)
					$this->existing_tax_classes[trim(mb_strtolower($tax_class->name))] = $tax_class->id;
			}

			$tax_class_name = trim($value);

			if (!strlen($tax_class_name))
				throw new Phpr_ApplicationException('Tax class name is not specified');

			$key = mb_strtolower($tax_class_name);
			if (array_key_exists($key, $this->existing_tax_classes))
				return $this->existing_tax_classes[$key];

			$tax_class = new Shop_TaxClass();
			$tax_class->name = $tax_class_name;
			$tax_class->rates = array(
				array('country'=>'*', 'state'=>'*', 'rate'=>0, 'priority'=>1, 'compound'=>0, 'tax_name'=>'TAX', 'zip'=>'', 'city'=>'')
			);
			$tax_class->save();

			$this->existing_tax_classes[$key] = $tax_class->id;
			return $this->existing_tax_classes[$key];
		}
		
		protected function format_url_name($url_name, $ignore_slash = false)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			$url_name = str_replace('&', 'and', $url_name);
			if($ignore_slash)
				$url_name = preg_replace("#[^a-z0-9/_-]#i", $separator, $url_name);
			else
				$url_name = preg_replace('/[^a-z0-9_-]/i', $separator, $url_name);
			$url_name = str_replace($separator.$separator, $separator, $url_name);
			if (substr($url_name, -1) == $separator)
				$url_name = substr($url_name, 0, -1);
				
			return trim(mb_strtolower($url_name));
		}
		
		protected function prepare_category_url_name($category_name, $parent_id = null)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			//allow slashes in category name if "Enable category URL nesting" enabled
			$url_name = $this->format_url_name($category_name, Shop_ConfigurationRecord::get()->nested_category_urls);

			$orig_url_name = $url_name;
			$counter = 1;
			$sql = 'select count(*) from shop_categories where url_name=:url_name';
			//if "Prepend parent category URL" enabled, url name should be unique only between categories with the same parent
			if(Shop_ConfigurationRecord::get()->category_urls_prepend_parent && Shop_ConfigurationRecord::get()->nested_category_urls)
			{
				if($parent_id)
					$sql .= ' and category_id =:parent_id';
				else
					$sql .= ' and category_id is null';
			}
			while (Db_DbHelper::scalar($sql, array('url_name'=>$url_name, 'parent_id' => $parent_id)))
			{
				$url_name = $orig_url_name.$separator.$counter;
				$counter++;
			}
			return $url_name;
		}
		
		protected function prepare_product_url_name($product_name, $product_id)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			$url_name = $this->format_url_name($product_name);

			$orig_url_name = $url_name;
			$counter = 1;
			
			$product_filter = $product_id ? ' and id <> :id' : null;
			
			while (Db_DbHelper::scalar('select count(*) from shop_products where url_name=:url_name'.$product_filter, array(
				'url_name'=>$url_name,
				'id'=>$product_id))
			)
			{
				$url_name = $orig_url_name.$separator.$counter;
				$counter++;
			}
			
			return $url_name;
		}
		
		protected function sanitize_product_url_name($name, $url_name, &$warnings, $line_number, $product_id)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			if (!preg_match('/^[0-9a-z_-]$/i', $url_name))
			{
				$new_url_name = $this->format_url_name($url_name);
				if (!strlen($new_url_name))
					$new_url_name = $this->prepare_product_url_name($name, $product_id);
				else
				{
					$product_filter = $product_id ? ' and id <> :id' : null;

					$orig_url_name = $new_url_name;
					$counter = 1;

					while (Db_DbHelper::scalar('select count(*) from shop_products where url_name=:url_name'.$product_filter, array('url_name'=>$new_url_name, 'id'=>$product_id)))
					{
						$new_url_name = $orig_url_name.$separator.$counter;
						$counter++;
					}
				}
			} else
				return $url_name;
			
			if ($new_url_name != $url_name)
				$this->add_warnings_line($warnings, $line_number, 'Product URL Name has been fixed. Original value: "'.$url_name.'", new value: "'.$new_url_name.'"');
			
			return $new_url_name;
		}
		
		protected function add_warnings_line(&$warnings, $line, $message)
		{
			if (!array_key_exists($line, $warnings))
				$warnings[$line] = $message;
			else
				$warnings[$line] .= "\n".$message;
		}
		
		protected function prepare_manufacturer_url_name($name)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			$url_name = $this->format_url_name($name);

			$orig_url_name = $url_name;
			$counter = 1;
			while (Db_DbHelper::scalar('select count(*) from shop_manufacturers where url_name=:url_name', array('url_name'=>$url_name)))
			{
				$url_name = $orig_url_name.$separator.$counter;
				$counter++;
			}
			
			return $url_name;
		}
		
		protected function extract_product_images($value)
		{
			$result = array();
			$value = explode(',', trim($value));
			
			foreach ($value as $path)
			{
				$path = trim($path);
				if (strlen($path))
				{
					$result[] = str_replace('\\', '/', $path);
				}
			}
			
			return $result;
		}
		
		protected function create_product(&$bind)
		{
			$this->init_sku_cache();
			$product_fields = $bind;
			$product_fields['created_at'] = $this->get_current_time();
			$product_fields['created_user_id'] = null;

			$user = Phpr::$security->getUser();
			if ($user)
				$product_fields['created_user_id'] = $user->id;

			if (
				(!array_key_exists('grouped_sort_order', $bind) || !strlen($bind['grouped_sort_order'])) && 
				(!array_key_exists('csv_import_parent_sku', $bind) || !strlen($bind['csv_import_parent_sku']))
			)
				$bind['grouped_sort_order'] = -1;
			
			$this->unset_helper_csv_fields($product_fields);
			$product_fields = $this->unset_unknown_product_fields($product_fields);
			$this->sql_insert('shop_products', $product_fields);
			$id = Db_DbHelper::driver()->get_last_insert_id();

			if (array_key_exists('sku', $bind))
				$this->sku_cache[mb_strtoupper($bind['sku'])] = $id;
			
			Shop_Product::update_total_stock_value($id);
			
			return $id;
		}
		
		protected function update_product_fields($existing_product_id, $bind)
		{

			$product_fields = $bind;
			$product_fields['updated_at'] = $this->get_current_time();
			$product_fields['updated_user_id'] = null;

			$user = Phpr::$security->getUser();
			if ($user)
				$product_fields['updated_user_id'] = $user->id;
				
			$this->unset_helper_csv_fields($product_fields);
			$product_fields = $this->unset_unknown_product_fields($product_fields);
			$this->sql_update('shop_products', $product_fields, 'id='.$existing_product_id);
			
			Shop_Product::update_total_stock_value($existing_product_id);
			
			return $existing_product_id;
		}
		
		protected function unset_helper_csv_fields(&$product_fields)
		{
			$helper_fields = array('csv_import_parent_sku', 'csv_related_sku', 'csv_import_om_flag', 'csv_import_om_parent_sku');
			foreach ($helper_fields as $helper_field)
			{
				if (array_key_exists($helper_field, $product_fields))
					unset($product_fields[$helper_field]);
			}
		}
		
		protected function unset_unknown_product_fields(&$product_fields)
		{
			$result = array();
			foreach ($product_fields as $name=>$value)
			{
				if (in_array($name, $this->product_columns))
					$result[$name] = $value;
			}
			
			return $result;
		}

		protected function update_parent_id($product_id, $parent_sku, &$warnings = null, $line_number = null, $product_sku = null, $original_parent_sku = null)
		{
			$this->init_sku_cache();
			
			if (!strlen($parent_sku))
			{
				$bind = array('grouped'=>null, 'product_id'=>null);
				$this->update_product_fields($product_id, $bind);
			} else
			{
				if (!array_key_exists($parent_sku, $this->sku_cache))
				{
					if(isset($warnings) && $line_number && isset($original_parent_sku))
						$this->add_warnings_line($warnings, $line_number, 'Product was not grouped: parent SKU '.$original_parent_sku.' does not exist.');
					return;
				}
				
				$parent_product_id = $this->sku_cache[$parent_sku];
				
				if($parent_product_id == $product_id)
				{
					if(isset($warnings) && $line_number && isset($original_parent_sku))
						$this->add_warnings_line($warnings, $line_number, 'Product was not grouped: SKU '.$product_sku.' cannot be grouped with itself.');
					return;
				}
				
				/* Prevent multi level grouped products */
				//if parent product is a grouped product on its own, don't allow the grouping
				$parents_parent_product_id = Db_DbHelper::scalar('select product_id from shop_products where id=:id', array('id'=>$parent_product_id));
				if($parents_parent_product_id)
				{
					if(isset($warnings) && $line_number && isset($original_parent_sku))
						$this->add_warnings_line($warnings, $line_number, 'Product was not grouped: SKU '.$original_parent_sku.' has a parent product and cannot become a parent product itself.');
					return;
				}
				//if current product has grouped products, don't allow it to get a parent
				$grouped_products = Db_DbHelper::scalar('select count(*) from shop_products where product_id=:id', array('id'=>$product_id));
				if($grouped_products > 0)
				{
					if(isset($warnings) && $line_number && isset($product_sku))
						$this->add_warnings_line($warnings, $line_number, 'Product was not grouped: SKU '.$product_sku.' cannot be grouped, because it already contains other grouped products.');
					return;
				}
				
				$grouped_sort_order = Db_DbHelper::scalar('select grouped_sort_order from shop_products where id=:id', array('id'=>$product_id));

				$bind = array('grouped'=>1, 'product_id'=>$parent_product_id);
				if (!strlen($grouped_sort_order))
					$bind['grouped_sort_order'] = $product_id;

				$this->update_product_fields($product_id, $bind);
				Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:shop_product_id', array('shop_product_id'=>$product_id));
			}
		}
		
		protected function update_related_products($product_id, $related_sku, &$warnings, $line_number)
		{
			$this->init_sku_cache();

			Db_DbHelper::query('delete from shop_related_products where master_product_id=:product_id', array('product_id'=>$product_id));

			$related_sku_list = explode('|', $related_sku);
			foreach ($related_sku_list as $related_product_sku)
			{
				$related_product_sku = trim($related_product_sku);
				if (!strlen($related_product_sku))
					continue;

				if (!array_key_exists($related_product_sku, $this->sku_cache))
				{
					$this->add_warnings_line($warnings, $line_number, 'Related product with SKU "'.$related_product_sku.'" not found.');
					continue;
				}

				$related_product_id = $this->sku_cache[$related_product_sku];
				Db_DbHelper::query(
					'insert into shop_related_products(master_product_id, related_product_id) 
						values (:master_product_id, :related_product_id)',
					array(
						'master_product_id'=>$product_id,
						'related_product_id'=>$related_product_id
					)
				);
			}
		}
		
		protected function init_sku_cache()
		{
			if ($this->sku_cache !== null)
				return;

			$this->sku_cache = array();
			$products = Db_DbHelper::objectArray('select id, upper(sku) as sku from shop_products where not (exists(select * from db_deferred_bindings where detail_class_name=\'Shop_Product\' and master_relation_name=\'grouped_products_all\' and detail_key_value=shop_products.id))');

			/*
			 * Excluded not commited deferred bindings from the SKU list
			 */
			
			foreach ($products as $product)
				$this->sku_cache[$product->sku] = $product->id;
		}
		
		protected function get_current_time()
		{
			if ($this->current_time)
				return $this->current_time;

			return $this->current_time = Phpr_DateTime::now()->toSqlDateTime();
		}
		
		protected function set_product_categories($product_id, $categories)
		{
		    $existing_links = Db_DbHelper::scalarArray('select shop_category_id from shop_products_categories where shop_product_id=:shop_product_id', array('shop_product_id'=>$product_id));
			$existing_links = array_flip($existing_links);

			foreach ($categories as $category_id)
			{
				if (!array_key_exists($category_id, $existing_links))
				{
					Db_DbHelper::query('insert into shop_products_categories(shop_product_id, shop_category_id) values (:shop_product_id, :shop_category_id)', array('shop_product_id'=>$product_id, 'shop_category_id'=>$category_id));
				}
			}
			
			foreach ($existing_links as $category_id=>$val)
			{
				if (!in_array($category_id, $categories))
					Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:shop_product_id and shop_category_id=:shop_category_id', array('shop_product_id'=>$product_id, 'shop_category_id'=>$category_id));
			}
		}
		
		protected function set_product_groups($product_id, $product_groups)
		{
			$existing_links = Db_DbHelper::scalarArray('select shop_custom_group_id from shop_products_customgroups where shop_product_id=:shop_product_id', array('shop_product_id'=>$product_id));
			$existing_links = array_flip($existing_links);

			foreach ($product_groups as $product_group_id)
			{
				if (!array_key_exists($product_group_id, $existing_links))
				{
					Db_DbHelper::query('insert into shop_products_customgroups(shop_product_id, shop_custom_group_id) values (:shop_product_id, :shop_custom_group_id)', array('shop_product_id'=>$product_id, 'shop_custom_group_id'=>$product_group_id));
				}
			}

			foreach ($existing_links as $product_group_id=>$val)
			{
				if (!in_array($product_group_id, $product_groups))
					Db_DbHelper::query('delete from shop_products_customgroups where shop_product_id=:shop_product_id and shop_custom_group_id=:shop_custom_group_id', array('shop_product_id'=>$product_id, 'shop_custom_group_id'=>$product_group_id));
			}
		}
		
		protected function set_price_tiers($product_id, $price_tiers)
		{
			$product_price_tiers = Db_DbHelper::objectArray('select * from shop_tier_prices where product_id=:product_id', array('product_id'=>$product_id));
			$price_tiers = unserialize($price_tiers);

			/*
			 * Delete removed price tiers
			 */

			foreach ($product_price_tiers as $product_price_tier)
			{
				$tier_found = false;
				foreach ($price_tiers as $csv_price_tier)
				{
					if ($csv_price_tier->customer_group_id == $product_price_tier->customer_group_id && $csv_price_tier->quantity == $product_price_tier->quantity)
					{
						$tier_found = true;
						break;
					}
				}
				
				if (!$tier_found)
					Db_DbHelper::query('delete from shop_tier_prices where id=:id', array('id'=>$product_price_tier->id));
			}

			/*
			 * Add and update existing price tiers
			 */
			
			$price_tiers_compiled_updated = array();
			foreach ($price_tiers as $price_tier)
			{
				$existing_tier_id = false;
				foreach ($product_price_tiers as $product_price_tier)
				{
					if ($price_tier->customer_group_id == $product_price_tier->customer_group_id && $price_tier->quantity == $product_price_tier->quantity)
					{
						$existing_tier_id = $product_price_tier->id;
						break;
					}
				}
				
				if ($existing_tier_id === false)
				{
					if($price_tier->customer_group_id != '')
						Db_DbHelper::query('insert into shop_tier_prices(product_id, customer_group_id, quantity, price) values (:product_id, :customer_group_id, :quantity, :price)', array('product_id'=>$product_id, 'customer_group_id'=>$price_tier->customer_group_id, 'quantity'=>$price_tier->quantity, 'price' => $price_tier->price));
					else 
						Db_DbHelper::query('insert into shop_tier_prices(product_id, quantity, price) values (:product_id, :quantity, :price)', array('product_id'=>$product_id, 'customer_group_id'=>$price_tier->customer_group_id, 'quantity'=>$price_tier->quantity, 'price' => $price_tier->price));

					$tier_id = Db_DbHelper::driver()->get_last_insert_id();
				} else {
					Db_DbHelper::query('update shop_tier_prices set price=:price where id=:id', array('price'=>$price_tier->price, 'id'=>$existing_tier_id));
					$tier_id = $existing_tier_id;
				}
				
				$tier_array = array();
				$tier_array['customer_group_id'] = $price_tier->customer_group_id;
				$tier_array['quantity'] = $price_tier->quantity;
				$tier_array['price'] = $price_tier->price;
				$tier_array['tier_id'] = $tier_id;
				$price_tiers_compiled_updated[] = (object)$tier_array;
			}

			$price_tiers_compiled_updated = serialize($price_tiers_compiled_updated);
			Db_DbHelper::query('update shop_products set tier_price_compiled=:tier_price_compiled where id=:id', array(
				'tier_price_compiled'=>$price_tiers_compiled_updated,
				'id'=>$product_id
			));
		}

		protected function set_product_attributes($product_id, $attributes)
		{
			foreach ($attributes as $attr_id=>$values)
			{
				$attr_name = mb_substr($attr_id, 6);

				$bind = array('product_id'=>$product_id, 'attr_name'=>$attr_name);
				Db_DbHelper::query('delete from shop_product_properties where product_id=:product_id and name=:attr_name', $bind);
				
				$values = explode('|', $values);
				foreach ($values as $value)
				{
					$value = trim($value);

					if (strlen($value))
					{
						$bind = array('product_id'=>$product_id, 'attr_name'=>$attr_name, 'value'=>$value);
						Db_DbHelper::query('insert into shop_product_properties(product_id, name, value) values (:product_id, :attr_name, :value)', $bind);
						Db_DbHelper::query('update shop_product_properties set sort_order = LAST_INSERT_ID() where id=LAST_INSERT_ID()');
					}
				}
			}
		}
		
		protected function prepare_files($files)
		{
			$result = array();

			foreach ($files as $file)
			{
				if (substr($file, 0, 1) == '/')
					$file = substr($file, 1);
					
				$file_found = false;
				$full_path = null;

				$full_path = $this->files_server_dir_path.'/'.$file;
				if (!file_exists($full_path))
					throw new Phpr_ApplicationException("Product file not found in the files directory: ".$full_path);
				
				$result[] = $full_path;
			}
			
			return $result;
		}
		
		protected function extract_product_files($value)
		{
			$result = array();
			$value = explode(',', trim($value));
			
			foreach ($value as $path)
			{
				$path = trim($path);
				if (strlen($path))
				{
					$result[] = str_replace('\\', '/', $path);
				}
			}
			
			return $result;
		}

		protected function prepare_images($images, $images_archive_dirs, $images_root_dir, &$warnings, $line_number)
		{
			$result = array();

			if (strlen($images_root_dir))
				$images_root_dir = $images_root_dir.'/';

			foreach ($images as $image)
			{
				if (substr($image, 0, 1) == '/')
					$image = substr($image, 1);
					
				$file_found = false;
				$full_path = null;
				foreach ($images_archive_dirs as $images_archive_dir)
				{
					$full_path = $images_archive_dir.'/'.$images_root_dir.$image;
					if (file_exists($full_path))
					{
						$file_found = true;
						break;
					}
				}
				
				if (!$file_found)
					$this->add_warnings_line($warnings, $line_number, "Image file not found: ".$full_path);
				else
					$result[] = $full_path;
			}
			
			return $result;
		}
		
		protected function set_product_images($product_id, $product_images)
		{
			/*
			 * Delete existing files
			 */
			$product_files = Db_DbHelper::queryArray("select * from db_files where master_object_class='Shop_Product' and master_object_id=:product_id and field=:field", 
				array(
					'product_id'=>$product_id,
					'field'=>'images'
				)
			);
			
			$file_obj = Db_File::create();
		
			foreach ($product_files as $file_data)
			{
				$file_obj->fill($file_data);
				$file_obj->delete();
			}

			/*
			 * Add new files
			 */

			foreach ($product_images as $image_path)
			{
				$file = Db_File::create();
				$file->is_public = true;

				$file->fromFile($image_path);
				$file->master_object_class = 'Shop_Product';
				$file->field = 'images';
				$file->master_object_id = $product_id;
				$file->save();
			}
		}
		
		protected function set_product_files($product_id, $product_files)
		{
			Db_DbHelper::query("delete from db_files where master_object_class='Shop_Product' and master_object_id=:product_id and field=:field", array(
				'product_id'=>$product_id,
				'field'=>'files'
			));
			
			foreach ($product_files as $path)
			{
				$file = Db_File::create();
				$file->is_public = true;

				$file->fromFile($path);
				$file->master_object_class = 'Shop_Product';
				$file->field = 'files';
				$file->master_object_id = $product_id;
				$file->save();
			}
		}
		
		protected function parse_options_string($str)
		{
			$product_options = trim($str);
			if (!strlen($product_options))
				return array();
			
			$product_options = self::prepare_line_breaks($product_options);
			$options = explode("\n", $product_options);
			
			$options_processed = array();
			foreach ($options as $index=>$option)
			{
				$option_parts = explode(':', $option);
				if (count($option_parts) < 2)
					throw new Phpr_ApplicationException('Invalid option value: '.$option);
					
				$option_name = trim($option_parts[0]);
				$options_processed[$option_name] = str_replace("|", "\n", trim($option_parts[1]));
			}

			return $options_processed;
		}
		
		protected function check_deleted_options($product_id, $product_options)
		{
			$options = $this->parse_options_string($product_options);
			
			$product_options = Db_DbHelper::objectArray('
				select 
					shop_custom_attributes.name, 
					shop_custom_attributes.id
				from 
					shop_custom_attributes
				where 
					shop_custom_attributes.product_id=:product_id 
			', array('product_id'=>$product_id));

			$options_to_remove = array();
			$options_ids_by_name = array();
			foreach ($product_options as $product_option_data)
			{
				$options_ids_by_name[$product_option_data->name] = $product_option_data->id;
				
				if (!array_key_exists($product_option_data->name, $options))
				{
					if (!Shop_CustomAttribute::is_option_can_be_deleted($product_option_data->id))
						throw new Phpr_ApplicationException(sprintf('Cannot delete product option "%s" because there are orders referring to it.', $product_option_data->name));
				}
			}
		}
		
		protected function set_product_options($product_id, $product_options)
		{
			$options = $this->parse_options_string($product_options);
				
			/*
			 * Delete removed options
			 */
			
			$product_options = Db_DbHelper::objectArray('
				select 
					shop_custom_attributes.name, 
					shop_custom_attributes.id
				from 
					shop_custom_attributes
				where 
					shop_custom_attributes.product_id=:product_id 
			', array('product_id'=>$product_id));

			$options_to_remove = array();
			$options_ids_by_name = array();
			foreach ($product_options as $product_option_data)
			{
				$options_ids_by_name[$product_option_data->name] = $product_option_data->id;
				
				$option_found = false;
				if (!array_key_exists($product_option_data->name, $options))
					$options_to_remove[] = $product_option_data->id;
			}

			foreach ($options_to_remove as $option_id)
			{
				Db_DbHelper::query('delete from shop_custom_attributes where id=:id',
					array('id'=>$option_id)
				);
				
				Shop_CustomAttribute::cleanup_option_data($option_id);
			}

			/*
			 * Update existing options
			 */

			$index = count($product_options);
			foreach ($options as $option_name=>$option_values)
			{
				$bind = array(
					'name'=>$option_name,
					'product_id'=>$product_id,
					'attribute_values'=>$option_values,
					'option_key'=>md5($option_name),
					'sort_order'=>$index
				);
				$index++;

				if (array_key_exists($option_name, $options_ids_by_name))
				{
					$bind['option_id'] = $options_ids_by_name[$option_name];
					Db_DbHelper::query(
						'update shop_custom_attributes set attribute_values=:attribute_values where id=:option_id',
						$bind
					);
				}
				else
					Db_DbHelper::query(
						'insert into shop_custom_attributes(name, product_id, attribute_values, option_key, sort_order) values (:name, :product_id, :attribute_values, :option_key, :sort_order)',
						$bind
					);
			}
		}
		
		protected function set_product_extra_options($product_id, $extra_options)
		{
			Db_DbHelper::query('delete from shop_extra_options where product_id=:product_id and (option_in_set is null or option_in_set=0)',
				array('product_id'=>$product_id)
			);

			$extra_options = trim($extra_options);
			if (!strlen($extra_options))
				return;

			$extra_options = self::prepare_line_breaks($extra_options);
			$options = explode("\n", $extra_options);
			foreach ($options as $option)
			{
				$rows = explode("\n", $option);
				foreach ($rows as $row)
				{
					if (!strlen($row))
						continue;

					$row_data = explode('|', $row);
					if (count($row_data) < 2)
						throw new Phpr_ApplicationException('Invalid extra option value: '.$row. '. Extra option data should have at least 2 columns: option name and price.');
						
					$option_name = trim($row_data[0]);
					if (!strlen($option_name))
						throw new Phpr_ApplicationException('Invalid extra option value: '.$row. '. Option name is not specified.');
						
					$option_name = str_replace('\n', "\n", $option_name);
					
					$option_price = trim($row_data[1]);
					if (!strlen($option_price))
						throw new Phpr_ApplicationException('Invalid extra option value: '.$row. '. Option price is not specified.');

					if (!preg_match('/^(\-?[0-9]*\.[0-9]+|\-?[0-9]+)$/', $option_price))
						throw new Phpr_ApplicationException('Invalid extra option price value: '.$option_price.'. Please specify price as number: 0.35, 10, etc.');

					$group = null;
					if (count($row_data) > 2)
						$group = trim($row_data[2]);
						
					$bind = array(
						'description'=>$option_name,
						'product_id'=>$product_id,
						'price'=>$option_price,
						'option_key'=>md5($option_name),
						'group_name'=>$group
					);

					Db_DbHelper::query(
						'insert into shop_extra_options(description, product_id, price, option_key, group_name) values (:description, :product_id, :price, :option_key, :group_name)',
						$bind
					);
					
					$last_option_id = Db_DbHelper::driver()->get_last_insert_id();
					Db_DbHelper::query('update shop_extra_options set extra_option_sort_order=:sort_order where id=:id', array(
						'sort_order'=>$last_option_id,
						'id'=>$last_option_id
					));
					
					$images = null;
					if (count($row_data) > 3)
						$images = $row_data[3];
						
					if (strlen($images) && $this->import_images)
					{
						$extra_images = $this->prepare_images(
							$this->extract_product_images($images), 
							$this->image_archive_dirs, 
							$this->images_root_dir,
							$warnings,
							$line_number);
							
						foreach ($extra_images as $image_path)
						{
							$file = Db_File::create();
							$file->is_public = true;

							$file->fromFile($image_path);
							$file->master_object_class = 'Shop_ExtraOption';
							$file->field = 'images';
							$file->master_object_id = $last_option_id;
							$file->save();
						}
					}
				}
			}

		}
		
		protected function set_product_extra_set_links($product_id, $extra_sets)
		{
			Db_DbHelper::query('delete from shop_products_extra_sets where extra_product_id=:product_id',
				array('product_id'=>$product_id)
			);

			$extra_sets = trim(mb_strtolower($extra_sets));
			if (!strlen($extra_sets))
				return;
				
			if ($this->extra_set_cache == null)
			{
				$this->extra_set_cache = array();
				$sets = Db_DbHelper::objectArray('select * from shop_extra_option_sets');
				foreach ($sets as $set)
				{
					if (strlen($set->code))
						$this->extra_set_cache[$set->code] = $set->id;
				}
			}

			$extra_sets = explode('|', $extra_sets);
			foreach ($extra_sets as $code)
			{
				if (array_key_exists($code, $this->extra_set_cache))
				{
					Db_DbHelper::query('insert into shop_products_extra_sets(extra_product_id, extra_option_set_id) values (:product_id, :set_id)', array(
						'product_id'=>$product_id,
						'set_id'=>$this->extra_set_cache[$code]
					));
				}
			}
		}
		
		protected function validate_fields(&$bind, $existing_product, &$warnings, $line_number)
		{
			$updated_data = Backend::$events->fireEvent('shop:onOverrideProductCsvImportData', $bind);
			foreach ($updated_data as $data_array)
			{
				if (!is_array($data_array))
					continue;
					
				if (!array_key_exists('data', $data_array))
					continue;
					
				foreach ($data_array['data'] as $data_key=>$data_element)
					$bind[$data_key] = $data_element;
					
				if (!array_key_exists('warnings', $data_array))
					continue;
					
				foreach ($data_array['warnings'] as $warning)
					$this->add_warnings_line($warnings, $line_number, $warning);
			}

			if(!$existing_product && (!array_key_exists('product_type_id', $bind) || !strlen($bind['product_type_id'])))
				throw new Phpr_ApplicationException('Product type is not specified for a new product.');
			elseif ($existing_product && (!array_key_exists('product_type_id', $bind)))
				$this->add_warnings_line($warnings, $line_number, "Product type was not specified and was not updated.");
			
			if (!$existing_product && (!array_key_exists('name', $bind) || !strlen($bind['name'])))
				throw new Phpr_ApplicationException('Product name is not specified for a new product.');
			elseif ($existing_product && array_key_exists('name', $bind) && !strlen($bind['name']))
				throw new Phpr_ApplicationException('Product name is not specified.');
			
			if (!$existing_product && (!array_key_exists('price', $bind) || !strlen($bind['price'])))
				throw new Phpr_ApplicationException('Product price is not specified for a new product.');
			elseif ($existing_product && array_key_exists('price', $bind) && !strlen($bind['price']))
				throw new Phpr_ApplicationException('Product price is not specified.');

			if (!$existing_product && (!array_key_exists('tax_class_id', $bind) || !strlen($bind['tax_class_id'])))
				throw new Phpr_ApplicationException('Tax class is not specified for a new product.');
			elseif ($existing_product && array_key_exists('tax_class_id', $bind) && !strlen($bind['tax_class_id']))
				$this->add_warnings_line($warnings, $line_number, "Tax class was not specified and was not updated.");

			if (!$existing_product && (!array_key_exists('categories', $bind) || !strlen($bind['categories'])))
				throw new Phpr_ApplicationException('Categories are not specified for a new product.');
			elseif ($existing_product && array_key_exists('categories', $bind) && !strlen($bind['categories']))
				$this->add_warnings_line($warnings, $line_number, 'Product categories were not specified and were not updated.');
			
			if(!$existing_product && array_key_exists('on_sale', $bind) && $bind['on_sale'] && (!array_key_exists('sale_price_or_discount', $bind) || !strlen($bind['sale_price_or_discount'])))
			{
				$bind['on_sale'] = 0;
				$this->add_warnings_line($warnings, $line_number, 'No sale price or discount specified, product was not imported as on sale.');
			}
			if(array_key_exists('sale_price_or_discount', $bind) && strlen($bind['sale_price_or_discount']))
			{
				$sale_price_error = Shop_Product::is_sale_price_or_discount_invalid($bind['sale_price_or_discount']);
				if($sale_price_error)
					throw new Phpr_ApplicationException('Invalid sale price or discount specified: '.$bind['sale_price_or_discount'].'. '.$sale_price_error);
			}

			if (!array_key_exists('sku', $bind) || !strlen($bind['sku']))
				throw new Phpr_ApplicationException('Product SKU is not specified');
				
			if (array_key_exists('perproduct_shipping_cost', $bind))
				$bind['perproduct_shipping_cost'] = $this->parse_shipping_cost($bind['perproduct_shipping_cost']);

			if (array_key_exists('price_tiers', $bind))
			{
				$bind['tier_price_compiled'] = $this->parse_price_tiers($bind['price_tiers']);
				unset($bind['price_tiers']);
			}

			if (!$existing_product)
			{
				if (!array_key_exists('url_name', $bind) || !strlen($bind['url_name']))
					$bind['url_name'] = $this->prepare_product_url_name($bind['name'], $existing_product);

				if (!array_key_exists('enabled', $bind) || !strlen($bind['enabled']))
					$bind['enabled'] = 1;
			}
			
			if (array_key_exists('url_name', $bind) && strlen($bind['url_name']))
				$bind['url_name'] = $this->sanitize_product_url_name($bind['name'], $bind['url_name'], $warnings, $line_number, $existing_product);
		}
		
		protected function parse_price_tiers($price_tiers_str)
		{
			$price_tiers_str = trim($price_tiers_str);
			$result = array();
			if (strlen($price_tiers_str))
			{
				$price_tiers_str = self::prepare_line_breaks($price_tiers_str);
				$rows = explode("\n", $price_tiers_str);
				foreach ($rows as $row)
				{
					$row = trim($row);
					if (!strlen($row))
						continue;
					$cells = explode('|', $row);
					if (count($cells) != 3)
						throw new Phpr_ApplicationException('Invalid price tier specified: '.$row.'. Valid format for price tiers is: Customer Group|Quantity|Price (Guest|5|5.45 or *|1|23)');

					$customer_group = trim($cells[0]);
					if (!strlen($customer_group))
						throw new Phpr_ApplicationException('Missing customer group for price tier (use * to apply price tier to all customer groups).');
					if($customer_group != '*')
					{
						//check that the customer group exists
						if(!isset($this->existing_customer_groups)) $this->fetch_customer_groups();
						if(!array_key_exists(strtoupper($customer_group), $this->existing_customer_groups))
							throw new Phpr_ApplicationException('Invalid customer group specified: '.$customer_group.'. Customer group does not exist.');
						else $customer_group_id = $this->existing_customer_groups[strtoupper($customer_group)];
					}
					else $customer_group_id =  null;

					$quantity = trim($cells[1]);
					if (!preg_match('/^([0-9])+$/', $quantity))
						throw new Phpr_ApplicationException('Invalid price tier quantity: '.$quantity.'. Please specify the quantity as an integer: 10');

					$price = trim($cells[2]);
					if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $price))
						throw new Phpr_ApplicationException('Invalid price tier price: '.$price.'. Please specify the price as a number: 10 or 10.5');

					$tier_array = array();
					$tier_array['customer_group_id'] = $customer_group_id;
					$tier_array['quantity'] = $quantity;
					$tier_array['price'] = $price;
					$result[] = (object)$tier_array;
				}
			}

			return serialize($result);
		}

		protected function parse_shipping_cost($shipping_cost_str)
		{
			$shipping_cost_str = self::prepare_line_breaks(trim($shipping_cost_str));
			$result = array();

			if (strlen($shipping_cost_str))
			{
				if (preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $shipping_cost_str))
				{
					$result[] = array(
						'country'=>'*',
						'state'=>'*',
						'zip'=>'*',
						'cost'=>$shipping_cost_str
					);
				} else
				{
					$rows = explode("\n", $shipping_cost_str);
					foreach ($rows as $row)
					{
						$row = trim($row);
						if (!strlen($row))
							continue;

						$cells = explode('|', $row);
						if (count($cells) < 4)
							throw new Phpr_ApplicationException('Invalid shipping cost location specifier: '.$row.'. Valid format for per-product shipping cost is: CA|BC|*|10');

						$country = trim($cells[0]);
						if (!strlen($country))
							$country = '*';
						
						$state = trim($cells[1]);
						if (!strlen($state))
							$state = '*';
							
						$zip = trim($cells[2]);
						if (!strlen($zip))
							$zip = '*';

						$cost = trim($cells[3]);
						if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $cost))
							throw new Phpr_ApplicationException('Invalid shipping cost value: '.$cost.'. Please specify cost as a number: 10 or 10.5');

						$result[] = array(
							'country'=>$country,
							'state'=>$state,
							'zip'=>$zip,
							'cost'=>$cost
						);
					}
				}
			}
			
			return serialize($result);
		}

		protected function fetch_customer_groups()
		{
			if(!$this->existing_customer_groups)
			{
				$this->existing_customer_groups = array();
				$groups = Db_DbHelper::objectArray('select id, name from shop_customer_groups');
				if(count($groups))
				{
					foreach($groups as $group)
						$this->existing_customer_groups[strtoupper($group->name)] = $group->id;
				}
			}
		}
		
		protected function import_om_record(&$column_map, $row, &$warnings, $line_number)
		{
			$this->init_sku_cache();

			$bind = array();
			
			/*
			 * Create and normalize the data map
			 */

			foreach ($column_map as $column_index=>$db_names)
			{
				if (!array_key_exists($column_index, $row))
					continue;
				
				foreach ($db_names as $db_name)	
					$bind[$db_name] = trim($row[$column_index]);
			}
			
			$options = $bind['options'];
			unset($bind['options']);

			if (array_key_exists('price', $bind))
			{
				$bind['base_price'] = $bind['price'];
				unset($bind['price']);
			}
			
			if (array_key_exists('price_tiers', $bind))
			{
				$parsed_tiers = unserialize($this->parse_price_tiers($bind['price_tiers']));
				$tier_price = array();
				foreach ($parsed_tiers as $tier)
				{
					$tier_price[] = array(
						'group_id'=>$tier->customer_group_id,
						'quantity'=>$tier->quantity,
						'price'=>$tier->price
					);
				}
				
				$bind['tier_price'] = $tier_price;
				unset($bind['price_tiers']);
			}
			
			if (isset($bind['enabled']))
				$bind['disabled'] = !Core_CsvHelper::boolean($bind['enabled']);
				
			if (isset($bind['images']))
			{
				$product_images = $this->prepare_images(
					$this->extract_product_images($bind['images']), 
					$this->image_archive_dirs, 
					$this->images_root_dir,
					$warnings,
					$line_number);
				
				$bind['images'] = $product_images;
			}

			/*
			 * Prepare option list
			 */
			
			$record_options = array();
			$options = self::prepare_line_breaks($options);
			$options = explode("\n", $options);
			foreach ($options as $index=>$option)
			{
				$option_parts = explode(':', $option);
				if (count($option_parts) < 2)
					throw new Phpr_ApplicationException('Invalid option value: '.$option);

				$option_name = trim($option_parts[0]);
				$record_options[$option_name] = trim($option_parts[1]);
			}

			/*
			 * Find product_id by the product SKU
			 */
			
			if (!isset($row[$this->om_parent_sku_column_index]))
				throw new Phpr_ApplicationException('Parent product SKU is not specified for Option Matrix record');
				
			$sku = trim($row[$this->om_parent_sku_column_index]);
			if (!strlen($sku))
				throw new Phpr_ApplicationException('Parent product SKU is not specified for Option Matrix record');

			$sku_upper = mb_strtoupper($sku);
			if (!array_key_exists($sku_upper, $this->sku_cache))
				throw new Phpr_ApplicationException(sprintf('Option Matrix parent product with SKU "%s" not found', $sku) );

			$product_id = $this->sku_cache[$sku_upper];
			
			/*
			 * Match Option Matrix columns with CSV columns
			 */

			$result = $this->option_matrix_manager->add_or_update(
				$product_id, 
				$record_options, 
				$bind, 
				!$this->update_existing_sku);
				
			if ($result->status != Shop_OptionMatrixManager::status_skipped)
			{
				Backend::$events->fireEvent('shop:onAfterCsvOptionMatrixImport', $result, $bind, $product_id, $record_options, $row, $this->image_archive_dirs, $this->images_root_dir);
			}
				
			return $result;
		}

		/*
		* Function ensures only "\n" line breaks are in the string
		*/
		public static function prepare_line_breaks($string)
		{
			$replace = array("\r\n", "\n\r", "\r");
			$string = str_replace($replace, "\n", $string);
			return $string;
		}
	}
	
	function shop_product_import_img_extract($p_event, &$p_header)
	{
		global $products_import_csv_images_dir;

		$pos = mb_strpos($p_header['filename'], $products_import_csv_images_dir);
		$file_name = $p_header['filename'];
		if ($pos !== false)
			$file_name = $products_import_csv_images_dir.mb_substr($file_name, mb_strlen($products_import_csv_images_dir));

		$p_header['filename'] = $file_name;

		return 1;
	}

?>