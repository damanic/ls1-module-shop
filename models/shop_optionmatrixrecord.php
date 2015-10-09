<?php

	/**
	 * Represents {@link http://lemonstand.com/docs/understanding_option_matrix/ Option Matrix} record.
	 * Usually you don't need to access objects of this class directly. 
	 * @documentable
	 * @property integer $id Specifies the record database identifier.
	 * @property Db_DataCollection $images A collection of images assigned to the category. 
	 * Each element in the collection is an object of the {@link Db_File} class. You can use this property directly to 
	 * output category images, or use the {@link Shop_Category::image_url() image_url()} method. Not proxiable.
	 * @property boolean $disabled Determines whether the record is disabled.
	 * @property string $sku Specifies the product SKU.
	 * @property float $base_price Specifies the base price of the record.
	 * @property float $cost Specifies the product cost.
	 * @property boolean $on_sale Determines whether the product is on sale.
	 * @property string $sale_price_or_discount. Specifies the sale price or discount in the following format: 10, -10, 10%.
	 * @property integer $in_stock Specifies the number of items in stock.
	 * @property Phpr_DateTime $expected_availability_date Specifies the expected availability date.
	 * @property float $weight Specifies the product weight.
	 * @property float $width Specifies the product width.
	 * @property float $height Specifies the product height.
	 * @property float $depth Specifies the product depth.
	 * @see http://lemonstand.com/docs/understanding_option_matrix/ Understanding Option Matrix
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_OptionMatrixRecord extends Db_ActiveRecord implements Db_MemoryCacheable
	{
		protected static $option_matrix_record_cache = array();

		public $table_name = 'shop_option_matrix_records';
		
		protected static $record_cache = array();
		protected static $supported_field_cache = array();
		protected static $option_graph = array();
		protected static $cached_option_values = array();

		public $has_many = array(
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_OptionMatrixRecord' and field='images'", 'order'=>'sort_order, id', 'delete'=>true),
			'record_options'=>array('class_name'=>'Shop_OptionMatrixOption', 'delete'=>true, 'order'=>'shop_option_matrix_options.id', 'foreign_key'=>'matrix_record_id')
		);
		
		public $api_columns = array();

		public $custom_columns = array(
			'grid_data'=>db_text
		);
		
		public static function create()
		{
			return new self();
		}
		
		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_column('grid_data', 'Option Matrix')->invisible()->validation();
			$this->define_multi_relation_column('images', 'images', 'Images', $front_end ? null : '@name')->invisible();
			
			Backend::$events->fireEvent('shop:onExtendOptionMatrixRecordModel', $this, $context);
		}
		
		public function define_form_fields($context = null)
		{
			if ($context != 'grid')
				$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list');
			
			$columns = array();
			
			/*
			 * General parameters
			 */
			$columns['disabled'] = array('title'=>'Disabled', 'type'=>'checkbox', 'width'=>85, 'checked_class'=>'status-disabled', 'header_control'=>true, 'column_group'=>'Product');
			$columns['sku'] = array('title'=>'SKU', 'type'=>'text', 'width'=>100, 'column_group'=>'Product');
			$columns['images'] = array('title'=>'Images', 'type'=>'popup', 'editor_class'=>'Db_GridImagesEditor', 'images_field'=>'images', 'width'=>100, 'default_text'=>'0 images', 'column_group'=>'Product');
			
			/*
			 * Pricing
			 */

			$columns['base_price'] = array('title'=>'Price', 'type'=>'text', 'align'=>'right', 'editor_class'=>'Shop_GridTierPriceEditor', 'width'=>100, 'column_group'=>'Pricing');
			$columns['cost'] = array('title'=>'Cost', 'type'=>'text', 'align'=>'right', 'width'=>100, 'column_group'=>'Pricing');
			$columns['on_sale'] = array('title'=>'On Sale', 'type'=>'checkbox', 'width'=>80, 'header_control'=>true, 'column_group'=>'Pricing');
			$columns['sale_price_or_discount'] = array('title'=>'Sale Price or Discount', 'type'=>'text', 'align'=>'right', 'width'=>150, 'validation_type'=>'discount', 'column_group'=>'Pricing');

			/*
			 * Inventory
			 */

			$columns['in_stock'] = array('title'=>'In Stock', 'type'=>'text', 'width'=>80, 'align'=>'right', 'column_group'=>'Inventory Tracking');
			$columns['expected_availability_date'] = array('title'=>'Expected date', 'align'=>'right', 'type'=>'text', 'width'=>95, 'editor_class'=>'Db_GridDateEditor', 'column_group'=>'Inventory Tracking');
			$columns['allow_pre_order'] = array('title'=>'Allow pre-order', 'type'=>'checkbox', 'width'=>85, 'header_control'=>true, 'column_group'=>'Inventory Tracking');

			/*
			 * Shipping
			 */
			
			$columns['weight'] = array('title'=>'Weight', 'type'=>'text', 'align'=>'right', 'width'=>60, 'column_group'=>'Shipping');
			$columns['width'] = array('title'=>'Width', 'type'=>'text', 'align'=>'right', 'width'=>50, 'column_group'=>'Shipping');
			$columns['height'] = array('title'=>'Height', 'type'=>'text', 'align'=>'right', 'width'=>50, 'column_group'=>'Shipping');
			$columns['depth'] = array('title'=>'Depth', 'type'=>'text', 'align'=>'right', 'width'=>50, 'column_group'=>'Shipping');
			
			/*
			 * API
			 */
			
			$new_api_columns = Backend::$events->fireEvent('shop:onExtendOptionMatrix', $this, $context);

			if ($new_api_columns && is_array($new_api_columns))
			{
				foreach ($new_api_columns as $api_columns_definition)
				{
					if (is_array($api_columns_definition))
					{
						foreach ($api_columns_definition as $column_id=>$column_configuration)
						{
							$columns[$column_id] = $column_configuration;
							$this->api_columns[$column_id] = $column_configuration;
						}
					}
				}
			}
			
			/*
			 * Set validation types basing on the database column types
			 */
			
			foreach ($columns as $column_name=>&$column)
			{
				if (!isset($column['validation_type']))
				{
					$db_column = $this->column($column_name);
					if ($db_column)
						$column['validation_type'] = $db_column->type;
				}
			}

			/*
			 * Add grid form field
			 */
			
			$this->add_form_field('grid_data')->renderAs(frm_widget, array(
				'class'=>'Db_GridWidget', 
				'sortable'=>true,
				'scrollable'=>true,
				'maintain_data_indexes'=>true,
				'enable_csv_operations'=>false,
				'enable_search'=>true,
				'disable_toolbar'=>false,
				'toolbar_add_button'=>false,
				'toolbar_delete_button'=>false,
				'csv_file_name'=>'option-matrix',
				'columns'=>$columns,
				'use_data_source'=>true,
				'data_source_id'=>'option-matrix-grid-data',
				'horizontal_scroll'=>true,
				'page_size'=>15,
				'focus_first'=>true,
				'title_word_wrap'=>false,
				'column_group_configuration'=>array(
					'Options'=>array('class'=>'key')
				)
			))->noLabel();
		}
		
		public function reinitialize($record_id, $skip_fields)
		{
			$this->reset_relations();
			$this->reset_plain_fields($skip_fields);

			if ($record_id === null)
				$this->set_new_record();
			else {
				$row_data = Db_DbHelper::queryArray('select * from shop_option_matrix_records where id=?', $record_id);
				$this->fill_external($row_data);
			}
		}

		private function eval_tier_price($product, $customer_group_id, $quantity)
		{
			if (!strlen($this->base_price))
				return $product->eval_tier_price($customer_group_id, $quantity);
			
			$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
			$price = Shop_TierPrice::eval_tier_price($this->tier_price_compiled, $customer_group_id, $quantity, $product->name, $this->base_price, $product->tier_price_compiled);

			if (!strlen($price))
				return $product->eval_tier_price($customer_group_id, $quantity);
				
			return $price;
		}
		
		public function list_group_price_tiers($product, $group_id)
		{
			$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
			$base_price = $this->base_price;
			if (!$base_price)
				return $product->list_group_price_tiers($group_id);

			return Shop_TierPrice::list_group_price_tiers($this->tier_price_compiled, $group_id, $product->name, $base_price, $product->tier_price_compiled);
		}
		
		public function set_compiled_price_rules($price_rules, $rule_map)
		{
			$this->price_rules_compiled = serialize($price_rules);
			$this->price_rule_map_compiled = serialize($rule_map);

			Db_DbHelper::query('update shop_option_matrix_records set price_rules_compiled=:price_rules_compiled, price_rule_map_compiled=:price_rule_map_compiled where id=:id', array(
				'price_rules_compiled'=>$this->price_rules_compiled,
				'price_rule_map_compiled'=>$this->price_rule_map_compiled,
				'id'=>$this->id
			));
		}
		
		/**
		 * Determines whether a specified field can be loaded from the record.
		 * Not supported properties are loaded from a base product.
		 * @documentable
		 * @param string $field_name Specifies the field name.
		 * @return mixed Returns TRUE if the field could be loaded from the record. 
		 * Otherwise returns name of a product field. 
		 */
		public function is_property_supported($field_name)
		{
			if (array_key_exists($field_name, self::$supported_field_cache))
				return self::$supported_field_cache[$field_name];
			
			if ($this->has_column($field_name) || isset($this->has_models[$field_name]))
				return $supported_field_cache[$field_name] = true;
				
			$supported_fields = array(
				'price',
				'sale_price',
				'is_on_sale',
				'is_out_of_stock',
				'volume'
			);
			if (in_array($field_name, $supported_fields))
				return $supported_field_cache[$field_name] = true;
				
			return $field_name;
		}
		
		/**
		 * A static method for returning the record sale price. This method
		 * is used internally.
		 */
		public static function get_sale_price_static($product, $test_record, $data, $customer_group_id = null, $no_tax = false)
		{
			$test_record->on_sale = $data->on_sale;
			$test_record->sale_price_or_discount = $data->sale_price_or_discount;
			$test_record->price_rules_compiled = $data->price_rules_compiled;
			$test_record->tier_price_compiled = $data->tier_price_compiled;
			$test_record->base_price = $data->base_price;

			return $test_record->get_sale_price($product, 1, $customer_group_id, $no_tax);
		}
		
		/**
		 * A static method for returning the record price. This method
		 * is used internally.
		 */
		public static function get_price_static($product, $test_record, $data, $customer_group_id = null, $no_tax = false)
		{
			$test_record->on_sale = $data->on_sale;
			$test_record->sale_price_or_discount = $data->sale_price_or_discount;
			$test_record->price_rules_compiled = $data->price_rules_compiled;
			$test_record->tier_price_compiled = $data->tier_price_compiled;
			$test_record->base_price = $data->base_price;

			return $test_record->get_price($product, 1, $customer_group_id, $no_tax);
		}
		
		/**
		 * Copies Option Matrix records from one product to another
		 */
		public static function copy_records_to_product($src_product, $dest_product)
		{
			$options = Db_DbHelper::objectArray('select id, name from shop_custom_attributes where product_id=:product_id', array('product_id'=>$dest_product->id));
			$product_option_ids = array();
			foreach ($options as $option)
				$product_option_ids[$option->name] = $option->id;
			
			/*
			 * Load the list of product Option Matrix records
			 */
			
			$records = Db_DbHelper::queryArray('select * from shop_option_matrix_records where product_id=:product_id', array('product_id'=>$src_product->id));
			$record_fields_insert_str = null;
			$record_fields_values_str = null;
			
			$record_option_fields_insert_str = null;
			$record_option_fields_values_str = null;
			
			foreach ($records as $record)
			{
				if ($record_fields_insert_str === null)
				{
					$record_field_map = array();
					
					foreach ($record as $field=>$value)
					{
						if ($field == 'id')
							continue;

						$record_field_map[] = $field;
					}
						
					$record_fields_insert_str = implode(', ', $record_field_map);
					$record_fields_values_str = ':'.implode(', :', $record_field_map);
				}
				
				$record['product_id'] = $dest_product->id;

				/*
				 * Create Option Matrix record
				 */

				Db_DbHelper::query("insert into shop_option_matrix_records($record_fields_insert_str) values ($record_fields_values_str)", $record);
				$new_record_id = Db_DbHelper::driver()->get_last_insert_id();
				
				/*
				 * Copy Option Matrix option records
				 */
				
				$record_options = Db_DbHelper::queryArray('
					select 
						shop_option_matrix_options.*,
						shop_custom_attributes.name
					from 
						shop_option_matrix_options, 
						shop_custom_attributes 
					where
						matrix_record_id=:id
						and shop_custom_attributes.id=shop_option_matrix_options.option_id
				', 
				array('id'=>$record['id']));
				
				foreach ($record_options as $record_option)
				{
					if (!array_key_exists($record_option['name'], $product_option_ids))
						continue;
						
					if ($record_option_fields_insert_str === null)
					{
						$record_field_map = array();

						foreach ($record_option as $field=>$value)
						{
							if ($field == 'id' || $field == 'name')
								continue;

							$record_field_map[] = $field;
						}

						$record_option_fields_insert_str = implode(', ', $record_field_map);
						$record_option_fields_values_str = ':'.implode(', :', $record_field_map);
					}
					
					$record_option['matrix_record_id'] = $new_record_id;
					$record_option['option_id'] = $product_option_ids[$record_option['name']];

					unset($record_option['id']);
					Db_DbHelper::query("insert into shop_option_matrix_options($record_option_fields_insert_str) values ($record_option_fields_values_str)", $record_option);
				}
				
				/*
				 * Copy Option Matrix record files
				 */
				
				$files_ids = Db_DbHelper::scalarArray('
					select 
						id 
					from 
						db_files 
					where 
						master_object_class=:master_object_class 
						and master_object_id=:master_object_id
				', array(
					'master_object_class'=>'Shop_OptionMatrixRecord',
					'master_object_id'=>$record['id']
				));
				
				foreach ($files_ids as $file_id)
				{
					$file = Db_File::create()->find($file_id);
					if (!$file)
						continue;
					
					try
					{
						$file_copy = $file->copy();
						$file_copy->master_object_id = $new_record_id;
						$file_copy->master_object_class = 'Shop_OptionMatrixRecord';
						$file_copy->field = $file->field;;
						$file_copy->save();
					} catch (exception $ex) {}
				}
			}
		}
		
		/**
		 * Generates the hash value (index) for the specified options
		 */
		public static function generate_options_hash($options, $option_keys)
		{
			$processed_options = array();
			
			if (!$option_keys)
			{
				foreach ($options as $key=>$value)
					$processed_options[md5($key)] = $value;
			} else
				$processed_options = $options;
				
			ksort($processed_options);
			
			$values = array();
			foreach ($processed_options as $key=>$value)
				$values[] = $key.'-'.$value;

			return md5(implode('|', $values));
		}
		
		/**
		 * Updates the options hash value in the database
		 */
		public function set_options_hash($hash)
		{
			$this->options_hash = $hash;
			Db_DbHelper::query('update shop_option_matrix_records set options_hash=:hash where id=:id', array('id'=>$this->id, 'hash'=>$hash));
		}
		
		/*
		 * Interface methods
		 */
		
		/**
		 * Returns Option Matrix record by option values.
		 * The <em>$options</em> parameter should contain a list of product options and option values in the
		 * following format: ['Option name 1'=>'option value 1', 'Option name 2'=>'option value 2']
		 * or: ['option_key_1'=>'option value 1', 'option_key_2'=>'option value 2'].
		 * Option keys and values are case sensitive. See also <em>$option_keys</em> parameter.
		 * @documentable
		 * @param array $options Specifies product option values
		 * @param mixed $product Product object (Shop_Product) or product identifier.
		 * @param boolean $option_keys Indicates whether array keys in the $options parameter represent option keys (md5(name)) rather than option names. 
		 * Otherwise $options keys are considered to be plain option name.
		 * @return Shop_OptionMatrixRecord returns the Option Matrix record object or NULL.
		 */
		public static function find_record($options, $product, $option_keys = false)
		{
			$product_id = is_object($product) ? $product->id : $product;

			$options_cache_key = sha1(serialize($options).'_'.$product_id).'_'.($option_keys ? 'keys' : 'values');
			if (array_key_exists($options_cache_key, self::$option_matrix_record_cache))
				return self::$option_matrix_record_cache[$options_cache_key];
				
			$obj = self::create();
			$obj->where('product_id=?', $product_id);
			$obj->where('options_hash=?', self::generate_options_hash($options, $option_keys));
			$obj->order('id');

			$result = $obj->find();
			if (!$result)
				return self::$option_matrix_record_cache[$options_cache_key] = null;
				
			Backend::$events->fireEvent('shop:onAfterOptionMatrixRecordFound', $result, $options, $product, $option_keys);
				
			return self::$option_matrix_record_cache[$options_cache_key] = $result;
		}

		/**
		 * Resets Option Matrix internal cache.
		 * Option Matrix caches records within a single request using product options as a cache key.
		 * @documentable
		 */
		public static function reset_cache()
		{
			self::$option_matrix_record_cache = array();
		}

		/**
		 * Returns product price, taking into account tier pricing. Returns product price with tax included,
		 * if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} 
		 * option is enabled unless the <em>$no_tax</em> parameter value is FALSE.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @param integer $quantity Quantity for the tier price calculations.
		 * @param integer $customer_group_id {@link Shop_CustomerGroup Customer group} identifier.
		 * @param boolean $no_tax Forces the function to not include tax into the result even if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} option is enabled.
		 * @return float Returns product price.
		 */
		public function get_price($product, $quantity = 1, $customer_group_id = null, $no_tax = false)
		{
			if ($customer_group_id === null)
				$customer_group_id = Cms_Controller::get_customer_group_id();
				
			$price = $this->eval_tier_price($product, $customer_group_id, $quantity);
			if ($no_tax)
				return $price;

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns product sale price. Returns price with tax included,
		 * if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} 
		 * option is enabled unless the <em>$no_tax</em> parameter value is FALSE.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @param integer $quantity Quantity for the tier price calculations.
		 * @param integer $customer_group_id {@link Shop_CustomerGroup Customer group} identifier.
		 * @param boolean $no_tax Forces the function to not include tax into the result even if the {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ Display catalog/cart prices including tax} option is enabled.
		 * @return float Returns product sale price.
		 */
		public function get_sale_price($product, $quantity = 1, $customer_group_id = null, $no_tax = false)
		{
			if ($customer_group_id === null )
				$customer_group_id = Cms_Controller::get_customer_group_id();

			if($this->on_sale && strlen($this->sale_price_or_discount))
			{
				$price = $this->get_price($product, $quantity, $customer_group_id, true);
				$price = round(Shop_Product::get_set_sale_price($price, $this->sale_price_or_discount), 2);
				
				return $no_tax ? $price : Shop_TaxClass::apply_tax_conditional($product->tax_class_id, $price);
			}

			/*
			 * If this record has no applied price rules, fallback to the standard record or product price.
			 * (It is possible that we should fallback to the product's sale price instead)
			 */

			$price_rules = null;
			try
			{
				$price_rules = unserialize($this->price_rules_compiled);
			} catch (Exception $ex) {}

			if (!$price_rules) {

				$tier_price_layers = null;
				try
				{
					$tier_price_layers = unserialize($this->tier_price_compiled);
				} catch (Exception $ex) {}

				if (!Phpr::$config->get('OM_SALE_PRICE_FALLBACK') || strlen($this->base_price) || $tier_price_layers)
					return $this->get_price($product, $quantity, $customer_group_id, $no_tax);
				else {
					$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
					if ($no_tax)
						return $product->get_sale_price_no_tax($quantity, $customer_group_id);

					return $product->get_sale_price($quantity, $customer_group_id);
				}
			}

			$price_rules = array();
			try
			{
				$price_rules = unserialize($this->price_rules_compiled);
			} catch (Exception $ex)
			{
				$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
				throw new Phpr_ApplicationException('Error loading price rules for the "'.$product->name.'" product');
			}
			
			if (!array_key_exists($customer_group_id, $price_rules))
				return $this->get_price($product, $quantity, $customer_group_id, $no_tax);

			$price_tiers = $price_rules[$customer_group_id];
			$price_tiers = array_reverse($price_tiers, true);

			foreach ($price_tiers as $tier_quantity=>$price)
			{
				if ($tier_quantity <= $quantity)
				{
					$price = round($price, 2);
					return $no_tax ? $price : Shop_TaxClass::apply_tax_conditional($product->tax_class_id, $price);
				}
			}

			return $this->get_price($product, $quantity, $customer_group_id, $no_tax);
		}
		
		/**
		 * Returns the difference between the regular price and sale price of the product.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @param integer $quantity Quantity for the tier price calculations.
		 * @return float Returns the sale reduction value.
		 */
		public function get_sale_reduction($product, $quantity = 1, $customer_group_id = null)
		{
			$sale_price = $this->get_sale_price($product, $quantity, $customer_group_id, true);
			$original_price = $this->get_price($product, $quantity, $customer_group_id, true);
			return $original_price - $sale_price;
		}
		
		/**
		 * Returns TRUE if there are active catalog-level price rules affecting the product price or if the product is on sale ('On Sale' checkbox).
		 * @documentable
		 * @param mixed $product Product object (Shop_Product) or product identifier.
		 * @return boolean Returns TRUE if the product is on sale.
		 */
		public function is_on_sale($product)
		{
			return $this->get_price($product) <> $this->get_sale_price($product);
		}

		/**
		 * Returns TRUE if inventory tracking for the product is enabled and the product is out of stock.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @return boolean Returns TRUE if the product is out of stock.
		 */
		public function is_out_of_stock($product)
		{
			$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
			if (!$product->track_inventory)
				return false;

			$in_stock = SHop_OptionMatrix::get_property($this, 'in_stock', $product);

			if ($product->stock_alert_threshold !== null)
				return $in_stock <= $product->stock_alert_threshold;

			if ($in_stock <= 0)
			 	return true;

			return false;
		}

		/**
		 * Returns TRUE if inventory tracking for the product is enabled and the product has reached the low stock threshold.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @return boolean Returns TRUE if the product is out of stock.
		 */
		public function is_low_stock($product)
		{
			$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
			if (!$product->track_inventory)
				return false;

			$in_stock = Shop_OptionMatrix::get_property($this, 'in_stock', $product);

			if ($product->low_stock_threshold !== null)
				return $in_stock <= $product->low_stock_threshold;

			return false;
		}

		/**
		 * Returns the product volume.
		 * @documentable
		 * @param mixed $product Product object ({@link Shop_Product}) or product identifier.
		 * @return float Returns the volume.
		 */
		public function get_volume($product)
		{
			$product = is_object($product) ? $product : Shop_Product::find_by_id($product);
			
			$width = Shop_OptionMatrix::get_property($this, 'width', $product);
			$height = Shop_OptionMatrix::get_property($this, 'height', $product);
			$depth = Shop_OptionMatrix::get_property($this, 'depth', $product);
			
			return $width*$height*$depth;
		}
		
		/**
		 * Returns options associated with the record as string.
		 * The returned string has the following format: <em>Color: green, Size: large</em>.
		 * @documentable
		 * @param string Returns options as string.
		 */
		public function options_as_string()
		{
			return Db_DbHelper::scalar("
				select 
					group_concat(
						concat(shop_custom_attributes.name, ': ', shop_option_matrix_options.option_value)
					separator ', ')
				from 
					shop_custom_attributes, 
					shop_option_matrix_options, 
					shop_option_matrix_records
				where 
					shop_option_matrix_records.id=:id
					and shop_option_matrix_options.matrix_record_id=shop_option_matrix_records.id
					and shop_custom_attributes.id=shop_option_matrix_options.option_id
				order by
					shop_custom_attributes.sort_order", array('id'=>$this->id));
		}
		
		/**
		 * Returns options associated with the record as array of option keys and values.
		 * @documentable
		 * @param boolean $option_keys Specifies whether options should be presented with option keys instead of names.
		 * @return array Returns an array of option keys and values.
		 */
		public function get_options($option_keys = true)
		{
			$options = Db_DbHelper::objectArray("
				select 
					shop_custom_attributes.option_key as option_key,
					shop_custom_attributes.name as option_name,
					shop_option_matrix_options.option_value as option_value
				from 
					shop_custom_attributes, 
					shop_option_matrix_options, 
					shop_option_matrix_records
				where 
					shop_option_matrix_records.id=:id
					and shop_option_matrix_options.matrix_record_id=shop_option_matrix_records.id
					and shop_custom_attributes.id=shop_option_matrix_options.option_id
				order by
					shop_custom_attributes.sort_order", array('id'=>$this->id));
					
			$result = array();
			foreach ($options as $option)
			{
				$key = $option_keys ? $option->option_key : $option->option_name;
				$result[$key] = $option->option_value;
			}
				
			return $result;
		}

		/*
		 * Db_MemoryCacheable implementation
		 */
		
		/*
		 * Returns a record by its identifier. If the record exists in the cache,
		 * returns the cached value. If it doesn't exist, finds the record, 
		 * adds it to the cache and returns the record.
		 * @param int $record_id Specifies the record identifier. Can be NULL 
		 * if a new record is requested.
		 */
		public function get_record_cached($record_id)
		{
			if (!strlen($record_id))
				$record_id = -1;

			if (array_key_exists($record_id, self::$record_cache))
				return self::$record_cache[$record_id];
				
			if ($record_id > -1)
				return self::$record_cache[$record_id] = self::create()->find($record_id);
				
			return self::$record_cache[$record_id] = self::create();
		}
		
		public function before_delete($id=null)
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_order_items, shop_orders where option_matrix_record_id is not null and option_matrix_record_id=:id and shop_orders.id = shop_order_items.shop_order_id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete product because there are orders referring to it.');
		}
		
		/**
		 * Returns a list of available values for a specific option.
		 * The option values are determined basing on the available enabled Option Matrix records.
		 * @param Shop_Product $product Product object
		 * @param Shop_CustomAttribute $option Product option object.
		 * @param array $posted_options An array of option values.
		 */
		public static function get_available_option_values($product, $option, $posted_options)
		{
			/*
			 * Create the options graph for the product
			 */

			$graph = self::get_option_graph($product);
			
			/*
			 * If the graph is empty, return all options
			 */
			
			if (!$graph)
				return $option->list_values(false);
				
			/*
			 * Find the posted options in the graph and return available values
			 */
			
			$requested_level = 0;
			foreach ($product->options as $index=>$product_option) 
			{
				if ($option->id == $product_option->id)
				{
					$requested_level = $index;
					break;
				}
			}

			return self::find_graph_level($graph, $posted_options, 0, $requested_level);
		}
		
		/**
		 * Returns first available value set.
		 * @param Shop_Product $product Product object
		 * @param array $posted_options An array of option values.
		 * @return array Returns an array of option keys and values
		 */
		public static function get_first_available_value_set($product, $option, $posted_options)
		{
			$requested_level = 0;
			foreach ($product->options as $index=>$product_option) 
			{
				if ($option->id == $product_option->id)
				{
					$requested_level = $index;
					break;
				}
			}

			$graph = self::get_option_graph($product);

			$result = array();
			for ($i = $requested_level; $i < $product->options->count; $i++)
			{
				$values = self::find_graph_level($graph, $posted_options, 0, $i);
				$option_key = $product->options[$i]->option_key;
				if (!isset($posted_options[$option_key]) || !in_array($posted_options[$option_key], $values))
				{
					$option_value = $result[$option_key] = count($values) ? $values[0] : null;
					$posted_options[$option_key] = $option_value;
				} else
					$result[$option_key] = $posted_options[$option_key];
			}
			
			return $result;
		}
		
		protected static function get_option_graph($product)
		{
			if (array_key_exists($product->id, self::$option_graph))
				return self::$option_graph[$product->id];
				
			$cache_key = 'om-graph-'.$product->id;

			if ($cached_graph = self::get_cached_option_graph($product, $cache_key))
				return $cached_graph;

			/*
			 * Load a list of available Option Matrix records with option identifiers and corresponding values
			 */
			
			$records = Db_DbHelper::scalarArray("
				select 
					(select group_concat(concat('--', option_id, ':', option_value, '--') separator '') from shop_option_matrix_options where  matrix_record_id=shop_option_matrix_records.id) as options
				from
					shop_option_matrix_records
				where 
					shop_option_matrix_records.product_id=:product_id
					and (disabled is null or disabled = 0)", array('product_id'=>$product->id));

			$graph = array();
			
			$options = $product->options;
			if ($options->count) 
			{
				$options_array = $product->options->as_array();
				$graph = self::build_graph_level($records, $product, $options_array);
			}

			self::$option_graph[$product->id] = $graph;

			$product_update_time = $product->updated_at ? $product->updated_at : $product->created_at;
			if ($product_update_time)
			{
				$cache = Core_CacheBase::create();
				$cache->set($cache_key, array('mtime'=>$product_update_time->getInteger(), 'data'=>$graph), 3600);
			}

			return $graph;
		}
		
		protected static function get_cached_option_graph($product, $cache_key)
		{
			$cache = Core_CacheBase::create();
			$cache_item = $cache->get($cache_key);
			if (!$cache_item)
				return false;
			
			if (!is_array($cache_item) || !array_key_exists('mtime', $cache_item))
				return false;
			
			$product_update_time = $product->updated_at ? $product->updated_at : $product->created_at;
			if (!$product_update_time)
				return false;

			if ($cache_item['mtime'] != $product_update_time->getInteger())
				return false;
				
			return $cache_item['data'];
		}
		
		protected static function find_graph_level(&$graph, &$posted_options, $level, $requested_level)
		{
			if ($level == $requested_level)
				return array_keys($graph['options']);

			if (!array_key_exists($graph['key'], $posted_options))
				return array();
				
			$requested_option_value = $posted_options[$graph['key']];

			if (!array_key_exists($requested_option_value, $graph['options']))
				return array();

			$level_graph = $graph['options'][$requested_option_value];
			return self::find_graph_level($level_graph, $posted_options, $level+1, $requested_level);
		}
		
		protected static function build_graph_level(&$records, $product, &$options, $level = 0, $filters = array())
		{
			$result = array();
			
			if (count($options) < ($level+1))
				return $result;
			
			$current_option = $options[$level];
			$option_values = self::get_option_values($current_option);

			$result['key'] = $current_option->option_key;
			$result['options'] = array();
			
			foreach ($option_values as $option_value)
			{
				foreach ($records as $record_info)
				{
					$record_found = true;
					$filters_updated = $filters;
					$filters_updated[] = '--'.$current_option->id.':'.$option_value.'--';
					
					foreach ($filters_updated as $filter)
					{
						if (strpos($record_info, $filter) === false)
						{
							$record_found = false;
							break;
						}
					}
					
					if ($record_found)
						$result['options'][$option_value] = self::build_graph_level($records, $product, $options, $level+1, $filters_updated);
				}
			}
			
			return $result;
		}
		
		protected static function get_option_values($option)
		{
			if (array_key_exists($option->id, self::$cached_option_values))
				return self::$cached_option_values[$option->id];

			return self::$cached_option_values[$option->id] = $option->list_values(false);
		}
		
		/**
		 * Allows to add new columns to the Option Matrix table. 
		 * Before you add new columns you should add corresponding columns to <em>shop_option_matrix_records</em> table,
		 * unless you are adding an image column.
		 * The event handler should return an array of new column definitions. Array keys should correspond the 
		 * table column names. Array values are associative arrays containing the column configuration. Example: 
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->add_event('shop:onExtendOptionMatrix', $this, 'extend_option_matrix');
		 * }
		 * 
		 * public function extend_option_matrix($model, $context) 
		 * {
		 *   $result = array(
		 *     'x_custom_int_column'=>array(
		 *         'title'=>'Custom integer', 
		 *         'type'=>'text', 
		 *         'align'=>'right', 
		 *         'width'=>50, 
		 *         'column_group'=>'Custom'
		 *     ),
		 *     'x_custom_date_column'=>array(
		 *         'title'=>'Custom date', 
		 *         'align'=>'right', 
		 *         'type'=>'text', 
		 *         'width'=>95, 
		 *         'editor_class'=>'Db_GridDateEditor', 
		 *         'column_group'=>'Custom'
		 *     ),
		 *     'x_custom_drop_down'=>array(
		 *         'title'=>'Custom date', 
		 *         'type'=>'dropdown', 
		 *         'width'=>100, 
		 *         'column_group'=>'Custom', 
		 *         'option_keys'=>array(1, 2), 
		 *         'option_values'=>array('Value 1', 'Value 2')
		 *     )
		 *   );
		 *   
		 *   return $result;
		 * }
		 * </pre>
		 * Column definitions support the following parameters:
		 * <ul>
		 *   <li><em>title</em> - defines the column title, required.</li>
		 *   <li><em>type</em> - defines the column type, required. Supported values are <em>text</em>, <em>dropdown</em>, <em>checkbox</em>, <em>popup</em> (applicable for image columns only).</li>
		 *   <li><em>align</em> - defines the column value alignment, required. Supported values are "left", "right".</li>
		 *   <li><em>width</em> - defines the column width, required.</li>
		 *   <li><em>column_group</em> - defines the column group name, required.</li>
		 *   <li><em>editor_class</em> - class name for a popup editor. Required value for date columns is <em>Db_GridDateEditor</em>. It is the only supported popup editor for API fields.</li>
		 *   <li><em>option_keys</em> - defines option keys for drop-down menus. Required if the column type is <em>dropdown</em>.</li>
		 *   <li><em>option_values</em> - defines option values for drop-down menus. Required if the column type is <em>dropdown</em>.</li>
		 *   <li><em>editor_class</em> - specifies a popup editor class. Required if the column type is <em>popup</em>. The only supported value is <em>Db_GridImagesEditor</em>.</li>
		 *   <li><em>images_field</em> - specifies an images column name. Required if the editor_class is <em>Db_GridImagesEditor</em>.</li>
		 * </ul>
		 * API columns are supported by CSV operations and can be accessed with {@link Shop_Product::om()}, {@link Shop_OrderItem::om()} and {@link Shop_CartItem::om()} methods.
		 * 
		 * In order to add an images field you should first define an images relation with the {@link shop:onExtendOptionMatrixRecordModel} event. The following example
		 * adds Extra Images column to the Option Matrix:
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->add_event('shop:onExtendOptionMatrix', $this, 'extend_option_matrix');
		 *   Backend::$events->add_event('shop:onExtendOptionMatrixRecordModel', $this, 'extend_option_matrix_model');
		 * }
		 * 
		 * public function extend_option_matrix($model, $context) 
		 * {
		 *   $result = array(
		 *     'x_extra_images'=>array(
		 *       'title'=>'Extra image', 
		 *       'type'=>'popup', 
		 *       'editor_class'=>'Db_GridImagesEditor', 
		 *       'images_field'=>'x_extra_images', 
		 *       'width'=>100, 
		 *       'column_group'=>'Custom')
		 *   );
		 * 
		 *   if ($context != 'grid')
		 *     $model->add_form_field('x_extra_images')->renderAs(frm_file_attachments)->renderFilesAs('image_list');
		 * 
		 *   return $result;
		 * }
		 * 
		 * public function extend_option_matrix_model($model, $context)
		 * {
		 *   $front_end = Db_ActiveRecord::$execution_context == 'front-end';
		 * 
		 *   $model->add_relation('has_many', 'x_extra_images', array(
		 *     'class_name'=>'Db_File', 
		 *     'foreign_key'=>'master_object_id',
		 *     'conditions'=>"master_object_class='Shop_OptionMatrixRecord' and field='x_extra_images'",
		 *     'order'=>'sort_order, id',
		 *     'delete' => true)
		 *   );
		 * 
		 *   $model->define_multi_relation_column('x_extra_images', 'x_extra_images', 'Extra images', $front_end ? null : '@name')->invisible();
		 * }
		 * </pre>
		 * @event shop:onExtendOptionMatrix
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionMatrixRecordModel
		 * @see Shop_OptionMatrixManager
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @see Shop_OptionMatrixManager
		 * @param Shop_OptionMatrixRecord $model Option Matrix record.
		 * @param string $context Specifies the form execution context.
		 * @return array Returns an array of column definitions.
		 */
		private function event_onExtendOptionMatrix($model, $context) {}

		/**
		 * Allows to add new columns or relations to the Option Matrix model. 
		 * Adding new columns and relations is only required if you want to extend Option Matrix with an images field.
		 * This event should be used with the {@link shop:onExtendOptionMatrix} event. In the event handler
		 * you can define new relations and columns. The following example adds Extra Images column to the Option Matrix:
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->add_event('shop:onExtendOptionMatrix', $this, 'extend_option_matrix');
		 *   Backend::$events->add_event('shop:onExtendOptionMatrixRecordModel', $this, 'extend_option_matrix_model');
		 * }
		 * 
		 * public function extend_option_matrix($model, $context) 
		 * {
		 *   $result = array(
		 *     'x_extra_images'=>array(
		 *       'title'=>'Extra image', 
		 *       'type'=>'popup', 
		 *       'editor_class'=>'Db_GridImagesEditor', 
		 *       'images_field'=>'x_extra_images', 
		 *       'width'=>100, 
		 *       'column_group'=>'Custom')
		 *   );
		 * 
		 *   if ($context != 'grid')
		 *     $model->add_form_field('x_extra_images')->renderAs(frm_file_attachments)->renderFilesAs('image_list');
		 * 
		 *   return $result;
		 * }
		 * 
		 * public function extend_option_matrix_model($model, $context)
		 * {
		 *   $front_end = Db_ActiveRecord::$execution_context == 'front-end';
		 * 
		 *   $model->add_relation('has_many', 'x_extra_images', array(
		 *     'class_name'=>'Db_File', 
		 *     'foreign_key'=>'master_object_id',
		 *     'conditions'=>"master_object_class='Shop_OptionMatrixRecord' and field='x_extra_images'",
		 *     'order'=>'sort_order, id',
		 *     'delete' => true)
		 *   );
		 * 
		 *   $model->define_multi_relation_column('x_extra_images', 'x_extra_images', 'Extra images', $front_end ? null : '@name')->invisible();
		 * }
		 * </pre>
		 * @event shop:onExtendOptionMatrixRecordModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionMatrix
		 * @see Shop_OptionMatrixManager
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_OptionMatrixRecord $model Option Matrix record.
		 * @param string $context Specifies the form execution context.
		 */
		private function event_onExtendOptionMatrixRecordModel($model, $context) {}
			
		/**
		 * Triggered when an Option Matrix record is loaded from the database. 
		 * The event is triggered for front-end calls when an Option Matrix record is loaded for a specific product for 
		 * a specific set of product options.
		 * @event shop:onAfterOptionMatrixRecordFound
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionMatrix
		 * @param Shop_OptionMatrixRecord $model Specifies the loaded Option Matrix model
		 * @param array $options Specifies product option values
		 * @param mixed $product Product object (Shop_Product) or product identifier.
		 * @param boolean $option_keys Indicates whether array keys in the $options parameter represent option keys (md5(name)) rather than option names. 
		 * Otherwise $options keys are considered to be plain option name.
		 */
		private function event_onAfterOptionMatrixRecordFound($model, $options, $product, $option_keys) {}
	}

?>