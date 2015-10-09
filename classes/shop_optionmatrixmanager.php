<?

	/**
	 * Provides methods for creating and updating {@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} records.
	 * @documentable
	 * @see http://lemonstand.com/docs/understanding_option_matrix/ Understanding Option Matrix
	 * @see Shop_OptionMatrixRecord
	 * @package shop.classes
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_OptionMatrixManager
	{
		const status_ok = 'OK';
		const status_skipped = 'SKIPPED';
		const status_ok_warnings = 'OK-WITH-WARNINGS';

		const operation_add = 'ADD';
		const operation_update = 'UPDATE';
		
		protected $option_matrix_columns;
		protected $option_matrix_model;
		
		protected static $option_cache = array();
		
		/**
		 * Creates the Option Matrix Manager class instance.
		 * @documentable
		 * @return Shop_OptionMatrixManager Returns the manager object.
		 */
		public function __construct()
		{
			$this->option_matrix_model = Shop_OptionMatrixRecord::create();
			$this->option_matrix_model->init_columns_info();
			$this->option_matrix_model->define_form_fields();
			$grid_data_field = $this->option_matrix_model->find_form_field('grid_data');
			
			$this->option_matrix_columns = $grid_data_field->renderOptions['columns'];
		}
		
		/**
		 * Returns data field values for Option Matrix record identified with product options and product.
		 * @documentable
		 * @param mixed $product Specifies either product identifier or product object ({@link Shop_Product}) the record belongs to.
		 * @param array $options A list of the record options in the following format: ['Color'=>'Red', 'Size'=>'M']
		 * @return mixed Returns an object containing the record data or NULL if the record cannot be found.
		 */
		public function get_record($product, $options)
		{
			if (is_object($product))
				$product = $product->id;

			if (!count($product))
				throw new Phpr_ApplicationException('Please specify product or product identifier.');
			
			if (!count($options))
				throw new Phpr_ApplicationException('Please specify product options.');

			$filters = array();
			
			$hash = Shop_OptionMatrixRecord::generate_options_hash($options, false);
			
			$result = Db_DbHelper::object('select * from shop_option_matrix_records where product_id=:product_id and options_hash=:hash', array('product_id'=>$product, 'hash'=>$hash));
			if (!$result)
				return null;
				
			return $result;
		}
		
		/**
		 * Adds or updates Option Matrix record in the database.
		 * The <em>$options</em> parameter should contain data values to assign to the record, as an associative array.
		 * Below is a full list of supported fields:
		 * <ul>
		 *   <li><em>disabled</em> - determines whether the record is disabled. String value, accepts <em>yes</em> and <em>no</em> values.</li>
		 *   <li><em>sku</em> - specifies the product SKU.</li>
		 *   <li><em>images</em> - an array of absolute paths to image files.</li>
		 *   <li><em>base_price</em> - specifies the base price of the record.</li>
		 *   <li><em>tier_price</em> - an array of tier prices in the following format: [['group_id'=>10, 'quantity'=>4, 'price'=>10], ...]. Use NULL for <em>group_id</em> element to indicate "Any customer group".</li>
		 *   <li><em>cost</em> - specifies the product cost.</li>
		 *   <li><em>on_sale</em> - determines whether the product is on sale.</li>
		 *   <li><em>sale_price_or_discount</em> - specifies the sale price or discount in the following format: 10, -10, 10%.</li>
		 *   <li><em>in_stock</em> - specifies the number of items in stock.</li>
		 *   <li><em>expected_availability_date</em> - specifies the expected availability date in format YYYY-MM-DD.</li>
		 *   <li><em>allow_pre_order</em> - indicates whether pre-ordering is allowed.</li>
		 *   <li><em>weight</em> - specifies the product weight.</li>
		 *   <li><em>width</em> - specifies the product width.</li>
		 *   <li><em>height</em> - specifies the product height.</li>
		 *   <li><em>depth</em> - specifies the product depth.</li>
		 *   <li><em>API columns</em> - any columns added with {@link shop:onExtendOptionMatrix} event.</li>
		 * </ul>
		 * The method returns an object with the following fields: 
		 * <ul>
		 *   <li><em>status</em> - <em>OK</em>, <em>SKIPPED</em> or <em>OK-WITH-WARNINGS</em> string value.</li>
		 *   <li><em>operation</em> - <em>ADD</em> or <em>UPDATE</em> string value.</li>
		 *   <li><em>warnings</em> - an array of warnings.</li>
		 *   <li><em>id</em> - identifier of the added/updated record.</li>
		 * </ul>
		 * The method throws an exception if the operation cannot be executed.
		 * Usage example:
		 * <pre>
		 * $manager = new Shop_OptionMatrixManager();
		 *  
		 * $options = array(
		 *   'Color'=>'Red',
		 *   'Size'=>'Large'
		 * );
		 *  
		 * $data = array(
		 *   'disabled'=>'no',
		 *   'sku'=>'red-large',
		 *   'images'=>array(PATH_APP.'/temp/images/red-1.jpg', PATH_APP.'/temp/images/red-2.jpg'),
		 *   'base_price'=>50,
		 *   'cost'=>10,
		 *   'on_sale'=>true,
		 *   'allow_pre_order'=>true,
		 *   'sale_price_or_discount'=>'10%',
		 *   'in_stock'=>32,
		 *   'expected_availability_date'=>'2012-05-29',
		 *   'weight'=>50,
		 *   'width'=>12,
		 *   'height'=>13,
		 *   'depth'=>14
		 * );
		 *  
		 * $status = $manager->add_or_update($test_product, $options, $data);
		 * </pre>
		 * @documentable
		 * @param Shop_Product $product Specifies a product the record belongs to.
		 * @param array $options A list of the product options and option values in the following format: ['Color'=>'Red', 'Size'=>'M'].
		 * @param array $data Data values to assign to the record.
		 * @param boolean $skip_existing Skip the operation if a record with specified options already exists (do not update it).
		 * @return mixed Returns an object with the following fields: <em>status</em>, <em>operation</em>, <em>warnings</em>, <em>id</em>.
		 */
		public function add_or_update($product, $options, $data, $skip_existing = false)
		{
			$result = array(
				'status'=>null,
				'operation'=>null,
				'warnings'=>array(),
				'id'=>null
			);
			
			$result = (object)$result;
			
			/*
			 * Find existing record
			 */
			
			$existing_record = $this->get_record($product, $options);
			if (is_object($product))
				$product = $product->id;
				
			if ($existing_record && $skip_existing)
			{
				$result->status = self::status_skipped;
				return $result;
			}
			
				
			/*
			 * Load product options
			 */
			
			$product_options = $this->load_product_option_ids($product);
			
			/*
			 * Check whether all options exist in the product
			 */
			
			foreach ($options as $option_name=>$option_value)
			{
				if (!array_key_exists($option_name, $product_options))
					throw new Phpr_ApplicationException(sprintf('Option %s does not exist in the product.', $option_name));
			}
			
			/*
			 * Validate and normalize data fields
			 */
			
			$original_data = $data;
			
			if (array_key_exists('images', $data))
				unset($data['images']);

			if (array_key_exists('tier_price', $data))
				unset($data['tier_price']);
			
			foreach ($data as $field_name=>&$value)
			{
				$value = trim($value);

				if (array_key_exists($field_name, $this->option_matrix_columns))
				{
					$column_options = $this->option_matrix_columns[$field_name];
					
					if (!isset($column_options['validation_type']) || !isset($data[$field_name]))
						continue;

					if (!strlen($value) && $field_name != 'sale_price_or_discount')
						continue;

					$column_title = isset($column_options['title']) ? $column_options['title'] : $field_name;

					switch ($column_options['validation_type'])
					{
						case db_bool :
							$value = Core_CsvHelper::boolean($value);
						break;
						case db_float :
							if (!Core_Number::is_valid($value))
								throw new Phpr_ApplicationException(sprintf('Invalid numeric value in %s column: %s', $field_name, $column_title));
						break;
						case db_number :
							if (!Core_Number::is_valid_int($value))
								throw new Phpr_ApplicationException(sprintf('Invalid integer value in %s column: %s', $field_name, $column_title));
						break;
						case db_date :
							if (!preg_match('/[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $value))
								throw new Phpr_ApplicationException(sprintf('Invalid date value in %s column: %s', $field_name, $column_title));
						break;
						case 'discount' :
							$base_price_value = null;
							if ($field_name == 'sale_price_or_discount')
							{
								if (array_key_exists('base_price', $data))
									$base_price_value = $data['base_price'];
								else
									$base_price_value = $existing_record ? $existing_record->base_price : null;

								if (array_key_exists('on_sale', $data))
									$on_sale_value = $data['on_sale'];
								else
									$on_sale_value = $existing_record ? $existing_record->on_sale : null;

								if (!strlen($value) && $on_sale_value)
									throw new Phpr_ApplicationException('Please specify a sale price or discount or disable the "On Sale" feature');
							}

							if ($error = Shop_Product::is_sale_price_or_discount_invalid($value, $base_price_value))
								throw new Phpr_ApplicationException($error);
						break;
					}
				}
			}
			
			$normalized_data = array();
			foreach ($data as $data_key=>$data_value)
			{
				if (array_key_exists($data_key, $this->option_matrix_columns))
					$normalized_data[$data_key] = $data_value;
			}

			$data = $normalized_data;

			/*
			 * Validate images
			 */
			
			$record_images = array();
			$update_images = false;
			
			if (array_key_exists('images', $original_data))
			{
				$update_images = true;
				
				if (!is_array($original_data['images']))
					throw new Phpr_ApplicationException('The images parameter value should be an array');
					
				foreach ($original_data['images'] as $image_path)
				{
					if (!file_exists($image_path) || !is_file($image_path))
						$result->warnings[] = sprintf('Image file not found: %s', $image_path);
					else 
						$record_images[] = $image_path;
				}
			}
			
			/*
			 * Validate tier pricing
			 */
			
			if (array_key_exists('tier_price', $original_data))
			{
				$price_tier_data = $this->parse_tier_price_data($original_data['tier_price'], $product);
				
				if ($price_tier_data)
				{
					if (array_key_exists('base_price', $data))
						$base_price_value = $data['base_price'];
					else
						$base_price_value = $existing_record ? $existing_record->base_price : null;

					if (!strlen($base_price_value))
						$result->warnings[] = sprintf('Tier pricing has no effect without a base price value.');
				}
				
				$data['tier_price_compiled'] = serialize($price_tier_data);
			}
			
			/*
			 * Update or insert record
			 */
			
			$data['product_id'] = $product;
			
			if (!$existing_record)
			{
				/*
				 * Insert Option Matrix record 
				 */
				
				$this->option_matrix_model->sql_insert('shop_option_matrix_records', $data);
				$record_id = $this->option_matrix_model->last_insert_id('shop_option_matrix_records', 'id');
				
				/*
				 * Insert Option Matrix record options 
				 */

				$update_sql = 'insert into shop_option_matrix_options
					(matrix_record_id, option_id, option_value) values ';
				$db_base = new Db_Base();
				foreach ($options as $option_name=>$option_value)
				{
					$update_sql .= $db_base->prepare('(:record_id, :option_id, :option_value)', array(
							'record_id'=>$record_id, 
							'option_id'=>$product_options[$option_name], 
							'option_value'=>$option_value
					)).',';
				}
				Db_DbHelper::query(substr($update_sql, 0, -1));

				if ($options)
				{
					$hash = Shop_OptionMatrixRecord::generate_options_hash($options, false);
					Db_DbHelper::query('update shop_option_matrix_records set options_hash=:hash where id=:id', array('id'=>$record_id, 'hash'=>$hash));
				}
				
				$result->status = self::status_ok;
				$result->operation = self::operation_add;
				$result->id = $record_id;
			} else 
			{
				/*
				 * Update Option Matrix record 
				 */

				$this->option_matrix_model->sql_update('shop_option_matrix_records', $data, 'id='.$existing_record->id);
				
				$result->status = self::status_ok;
				$result->operation = self::operation_update;
				$result->id = $existing_record->id;
			}
			
			/*
			 * Process images
			 */
			
			if ($update_images)
				$this->set_record_images($record_images, $result->id);
			
			/*
			 * Update product's total stock value if the stock value has been changed
			 */
			
			if (array_key_exists('in_stock', $data))
			{
				if ($result->operation == self::operation_add || $data['in_stock'] != $existing_record->in_stock)
				
				Shop_Product::update_total_stock_value($product);
			}
			
			if (count($result->warnings))
				$result->status = self::status_ok_warnings;
				
			return $result;
		}
		
		protected function load_product_option_ids($product_id)
		{
			if (array_key_exists($product_id, self::$option_cache))
				return self::$option_cache[$product_id];

			$option_records = Db_DbHelper::objectArray('select name, id from shop_custom_attributes where product_id=:product_id', array('product_id'=>$product_id));
			$result = array();
			foreach ($option_records as $option)
				$result[$option->name] = $option->id;
				
			return self::$option_cache[$product_id] = $result;
		}
		
		protected function parse_tier_price_data($data, $product)
		{
			if (!is_array($data))
				throw new Phpr_ApplicationException('The tier price parameter value should be an array');
				
			$record_tier_prices = array();
			
			foreach ($data as $tier_data)
			{
				if (!array_key_exists('group_id', $tier_data))
					throw new Phpr_ApplicationException('Index "group_id" is not found in the tier price configuration.');

				if (!array_key_exists('quantity', $tier_data))
					throw new Phpr_ApplicationException('Index "quantity" is not found in the tier price configuration.');

				if (!array_key_exists('price', $tier_data))
					throw new Phpr_ApplicationException('Index "price" is not found in the tier price configuration.');

				$tier_data['quantity'] = trim($tier_data['quantity']);
				$tier_data['price'] = trim($tier_data['price']);
				$tier_data['group_id'] = trim($tier_data['group_id']);
				
				if (!Core_Number::is_valid_int($tier_data['group_id']))
					throw new Phpr_ApplicationException(sprintf('Invalid tier price group identifier value: %s', $tier_data['group_id']));

				if (!Core_Number::is_valid_int($tier_data['quantity']))
					throw new Phpr_ApplicationException(sprintf('Invalid tier price quantity value: %s', $tier_data['quantity']));

				if (!Core_Number::is_valid($tier_data['price']))
					throw new Phpr_ApplicationException(sprintf('Invalid tier price value: %s', $tier_data['price']));

				$group_id = null;
				$group_name = 'Any customer group';
				if (strlen($tier_data['group_id']))
				{
					$group = Shop_CustomerGroup::find_by_id($tier_data['group_id']);
					if (!$group)
						throw new Phpr_ApplicationException(sprintf('Customer group with identifier %s not found.', $tier_data['group_id']));

					$group_id = $group->id;
					$group_name = $group->name;
				} 
					
				/*
				 * Find price tier in the parent product
				 */
				
				if ($group_id !== null)
				{
					$tier_id = Db_DbHelper::scalar('select 
							id 
						from 
							shop_tier_prices 
						where 
							product_id=:product_id 
							and customer_group_id=:customer_group_id 
							and quantity=:quantity', array(
						'product_id'=>$product,
						'customer_group_id'=>$group_id,
						'quantity'=>$tier_data['quantity']
					));
				} else {
					$tier_id = Db_DbHelper::scalar('select 
							id 
						from 
							shop_tier_prices 
						where 
							product_id=:product_id 
							and customer_group_id is null
							and quantity=:quantity', array(
						'product_id'=>$product,
						'quantity'=>$tier_data['quantity']
					));
				}

				if (!$tier_id)
					throw new Phpr_ApplicationException(sprintf('Price tier for customer group "%s" and quantity "%s" not found in the parent product.', $group_name, $tier_data['quantity']));

				$record_tier_prices[$tier_id] = $tier_data['price'];
			}
			
			return $record_tier_prices;
		}

		protected function set_record_images($record_images, $record_id)
		{
			$record_files = Db_DbHelper::queryArray("select 
					* 
				from 
					db_files 
				where 
					master_object_class='Shop_OptionMatrixRecord' 
					and master_object_id=:record_id 
					and field=:field", 
				array(
					'record_id'=>$record_id,
					'field'=>'images'
				)
			);
			
			$file_obj = Db_File::create();

			foreach ($record_files as $file_data)
			{
				$file_obj->fill($file_data);
				$file_obj->delete();
			}
			
			foreach ($record_images as $image_path)
			{
				$file = Db_File::create();
				$file->is_public = true;

				$file->fromFile($image_path);
				$file->master_object_class = 'Shop_OptionMatrixRecord';
				$file->field = 'images';
				$file->master_object_id = $record_id;
				$file->save();
			}
		}
	}

?>