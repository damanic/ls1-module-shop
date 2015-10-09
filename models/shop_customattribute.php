<?

	/**
	 * Represents a {@link Shop_Product product} option.
	 * Objects of this class are available through the {@link Shop_Product::$options} property.
	 * @property integer $id Specifies the option record identifier.
	 * @documentable
	 * @property string $name Specifies the option name.
	 * @property string $attribute_values Specifies the option values as string - one value in each line.
	 * @see http://lemonstand.com/docs/displaying_product_options Displaying product options
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_CustomAttribute extends Db_ActiveRecord
	{
		public $table_name = 'shop_custom_attributes';
		protected $api_added_columns = array();

		public $implement = 'Db_Sortable';
		
		public $parent_product;

		protected static $id_name_cache = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('attribute_values', 'Values')->validation()->fn('trim')->required();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendOptionModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->comment('Specify the option name, e.g. "Colors" to display near the attribute drop-down menu.', 'above');
			$this->add_form_field('attribute_values')->renderAs(frm_textarea)->comment('Specify option values, e.g. "Red, Green, Blue" - one value per line.', 'above');

			Backend::$events->fireEvent('shop:onExtendOptionForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetOptionFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		/**
		 * Returns a list of the option values.
		 * @documentable
		 * @return array Returns an array of option values.
		 */
		public function list_values($limit_to_om = true)
		{
			if ($limit_to_om && $this->parent_product && Shop_ConfigurationRecord::get()->strict_option_values && $this->parent_product->has_om_records())
			{
				return $this->list_om_values();
			}
			
			$values = explode("\n", $this->attribute_values);
			$result = array();
			foreach ($values as $value)
			{
				if (strlen($value))
					$result[] = trim($value);
			}

			return $result;
		}
		
		/**
		 * Returns a list of the option values limited with the existing active Option Matrix record combination.
		 * @return array Returns an array of option values.
		 */
		protected function list_om_values()
		{
			return Shop_OptionMatrixRecord::get_available_option_values(
				$this->parent_product,
				$this, 
				Shop_ProductHelper::get_default_options($this->parent_product)
			);
		}
		
		public function copy()
		{
			$obj = new self();
			$obj->name = $this->name;
			$obj->attribute_values = $this->attribute_values;

			return $obj;
		}

		public function before_save($deferred_session_key = null) 
		{
			$this->option_key = md5($this->name);
		}
		
		/**
		 * Returns a full list of unique product option names.
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_the_search_page Creating the Search Page
		 * @return array Returns an array of option names.
		 */
		public static function list_unique_names()
		{
			return Db_DbHelper::scalarArray('select distinct name from shop_custom_attributes');
		}
		
		/**
		 * Returns a full list of unique product option values.
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_the_search_page Creating the Search Page
		 * @return array Returns an array of option values.
		 */
		public static function list_unique_values($name)
		{
			$values = Db_DbHelper::scalarArray('select distinct attribute_values from shop_custom_attributes where name=:name', array('name'=>$name));
			$result = array();
			foreach ($values as $value)
			{
				$value_set = explode("\n", $value);
				foreach ($value_set as $attr_value)
				{
					$attr_value = trim($attr_value);
					if (strlen($attr_value) && !in_array($attr_value, $result))
						$result[] = $attr_value;
				}
			}
			
			sort($result);

			return $result;
		}
		
		/**
		 * Checks whether a specified value exists in the option.
		 * @documentable
		 * @param string $option_value Specifies the option value.
		 * @return boolean Returns TRUE if a specified value exists in the option
		 */
		public function value_exists($option_value)
		{
			$values = $this->list_values();
			foreach ($values as $value)
			{
				if ($value == $option_value)
					return true;
			}
			
			return false;
		}
		
		/**
		 * Returns first value from the list of the option values.
		 * @return mixed Returns the first value. Returns FALSE in case
		 * if the option doesn't have any values.
		 */
		public function get_first_value($limit_to_om = true)
		{
			$values = $this->list_values($limit_to_om);
			if (count($values))
				return $values[0];

			return false;
		}
		
		public static function is_option_can_be_deleted($option_id)
		{
			$bind = array(
				'id'=>$option_id
			);

			return !Db_DbHelper::scalar('
				select 
					count(*) 
				from 
					shop_option_matrix_records, 
					shop_option_matrix_options,
					shop_order_items, 
					shop_orders
				where 
					shop_option_matrix_options.matrix_record_id=shop_option_matrix_records.id
					and shop_order_items.option_matrix_record_id is not null
					and shop_order_items.option_matrix_record_id=shop_option_matrix_records.id
					and shop_orders.id = shop_order_items.shop_order_id
					and option_id=:id
				', $bind);
		}
		
		public function can_delete()
		{
			return self::is_option_can_be_deleted($this->id);
		}
		
		public function before_delete($id=null)
		{
			if (!$this->can_delete())
				throw new Phpr_ApplicationException('Cannot delete product option because there are orders referring to it.');
		}
		
		public function after_delete()
		{
			self::cleanup_option_data($this->id);
		}
		
		public static function get_name_by_id($id)
		{
			if (!array_key_exists($id, self::$id_name_cache))
				self::$id_name_cache[$id] = Db_DbHelper::scalar('select name from shop_custom_attributes where id=:id', array('id'=>$id));
				
			return self::$id_name_cache[$id];
		}
		
		public static function cleanup_option_data($option_id)
		{
			/*
			 * Delete associated Option Matrix records (only if there are no options related to them).
			 */

			$bind = array('option_id'=>$option_id);

			$record_ids = Db_DbHelper::scalarArray('select ol.matrix_record_id as id from shop_option_matrix_options ol
				where ol.option_id = :option_id
				and not exists (select * from shop_option_matrix_options il where il.matrix_record_id=ol.matrix_record_id and option_id <> :option_id)
			', $bind);

			foreach ($record_ids as $id)
				Db_DbHelper::query('delete from shop_option_matrix_records where id=:id', array('id'=>$id));

			Db_DbHelper::query('delete from shop_option_matrix_options where option_id=:option_id', $bind);
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the product option model.
		 * The event handler should accept a single parameter - the product option object. To add new columns to the product option model, 
		 * call the {@link Db_ActiveRecord::define_column() define_column()} method of the product option object. Before you add new columns to the model, 
		 * you should add them to the database (the <em>shop_custom_attributes</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendOptionModel', $this, 'extend_option_model');
		 *   Backend::$events->addEvent('shop:onExtendOptionForm', $this, 'extend_option_form');
		 * }
		 * 
		 * public function extend_option_model($option)
		 * {
		 *   $option->define_column('x_color', 'Color');
		 * }
		 *      
		 * public function extend_option_form($option, $context)
		 * {
		 *   $option->add_form_field('x_color');
		 * }
		 * </pre>
		 * @event shop:onExtendOptionModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionForm
		 * @see shop:onGetOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_CustomAttribute $option Specifies the product option object.
		 */
		private function event_onExtendOptionModel($option) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Product Option form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendOptionModel} event. 
		 * To add new fields to the product option form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * product option object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendOptionModel', $this, 'extend_option_model');
		 *   Backend::$events->addEvent('shop:onExtendOptionForm', $this, 'extend_option_form');
		 * }
		 * 
		 * public function extend_option_model($option)
		 * {
		 *   $option->define_column('x_color', 'Color');
		 * }
		 *      
		 * public function extend_option_form($option, $context)
		 * {
		 *   $option->add_form_field('x_color');
		 * }
		 * </pre>
		 * @event shop:onExtendOptionForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionModel
		 * @see shop:onGetOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_CustomAttribute $option Specifies the product option object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendOptionForm($option, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendOptionForm} event.
		 * Usually you do not need to use this event for fields which represent 
		 * {@link http://lemonstand.com/docs/extending_models_with_related_columns data relations}. But if you want a standard 
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendOptionModel', $this, 'extend_option_model');
		 *   Backend::$events->addEvent('shop:onExtendOptionForm', $this, 'extend_option_form');
		 *   Backend::$events->addEvent('shop:onGetOptionFieldOptions', $this, 'get_options_field_options');
		 * }
		 * 
		 * public function extend_option_model($option)
		 * {
		 *   $option->define_column('x_color', 'Color');
		 * }
		 *      
		 * public function extend_option_form($product, $context)
		 * {
		 *   $option->add_form_field('x_color')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_options_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *     0 => 'Red',
		 *     1 => 'Green',
		 *     2 => 'Blue'
		 *     );
		 *      
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *        
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetOptionFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOptionModel
		 * @see shop:onExtendOptionForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetOptionFieldOptions($db_name, $field_value) {}
	}