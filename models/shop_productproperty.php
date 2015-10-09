<?php

	/**
	 * Represents a {@link Shop_Product product} attribute.
	 * @property integer $id Specifies the attribute record identifier.
	 * @property string $name Specifies the attribute name.
	 * @property string $value Specifies the attribute value.
	 * @documentable
	 * @see http://lemonstand.com/docs/displaying_a_list_of_product_attributes/ Displaying product attributes
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductProperty extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_properties';

		public $implement = 'Db_Sortable';
		public $custom_columns = array('value_pickup'=>db_text);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Attribute Name')->validation()->fn('trim')->required();

			$this->define_column('value_pickup', 'Value');
			$this->define_column('value', 'Value')->validation()->fn('trim');

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendAttributeModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name');
			$this->add_form_field('value_pickup')->renderAs(frm_dropdown)->emptyOption('<known attribute values>')->comment('Please enter a value to the text field below, or choose an existing value.', 'above')->cssClassName('relative');
			$this->add_form_field('value')->renderAs(frm_textarea)->size('small')->noLabel()->cssClassName('relative');

			Backend::$events->fireEvent('shop:onExtendAttributeForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}

		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetAttributeFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || ($options !== false && $current_key_value != -1))
					return $options;
			}
		
			return false;
		}
		
		public function copy()
		{
			$obj = self::create();
			$obj->name = $this->name;
			$obj->value = $this->value;
			
			return $obj;
		}
		
		public function get_value_pickup_options($key = -1)
		{
			$result = array();
			
			$name = mb_strtolower(trim($this->name));
			$values = Db_DbHelper::objectArray('select distinct id, value from shop_product_properties where lower(name)=:name group by value order by value', array('name'=>$name));
			foreach ($values as $value_obj)
			{
				$value = Phpr_Html::strTrim(str_replace("\n", " ", $value_obj->value), 40);
				$result[$value_obj->id] = $value;
			}
			
			return $result;
		}
		
		public static function list_unique_names()
		{
			return Db_DbHelper::scalarArray('select distinct name from shop_product_properties');
		}
		
		public function load_value($attribute_id)
		{
			if (!strlen($attribute_id))
				return;
				
			$this->value = Db_DbHelper::scalar('select value from shop_product_properties where id=:id', array('id'=>$attribute_id));
		}
		
		public static function list_unique_values($name)
		{
			$values = Db_DbHelper::scalarArray('select distinct value from shop_product_properties where name=:name', array('name'=>$name));
			$result = array();
			foreach ($values as $value)
			{
				if (strlen($value) && !in_array($value, $result))
					$result[] = $value;
			}

			sort($result);
			return $result;
		}

		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the product attribute model.
		 * The event handler should accept two parameters - the product attribute object and the form
		 * execution context string. To add new columns to the attribute model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the attribute object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_product_properties</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendAttributeModel', $this, 'extend_attribute_model');
		 *    Backend::$events->addEvent('shop:onExtendAttributeForm', $this, 'extend_attribute_form');
		 * }
		 * 
		 * public function extend_attribute_model($attribute, $context)
		 * {
		 *    $attribute->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_attribute_form($attribute, $context)
		 * {
		 *    $attribute->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendAttributeModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendAttributeForm
		 * @see shop:onGetAttributeFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductProperty $attribute Specifies the product attribute object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendAttributeModel($attribute, $context) {}

		/**
		 * Allows to add new fields to the Add/Edit attribute section of the Add/Edit Product form in the Administration Area.
		 * Usually this event is used together with the {@link shop:onExtendAttributeModel} event.
		 * To add new fields to the attribute form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * attribute object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendAttributeModel', $this, 'extend_attribute_model');
		 *    Backend::$events->addEvent('shop:onExtendAttributeForm', $this, 'extend_attribute_form');
		 * }
		 * 
		 * public function extend_attribute_model($attribute, $context)
		 * {
		 *    $attribute->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_attribute_form($attribute, $context)
		 * {
		 *    $attribute->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendAttributeForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendAttributeModel
		 * @see shop:onGetAttributeFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductProperty $attribute Specifies the product attribute object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendAttributeForm($attribute, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendAttributeForm} event.
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
		 *    Backend::$events->addEvent('shop:onExtendAttributeModel', $this, 'extend_attribute_model');
		 *    Backend::$events->addEvent('shop:onExtendAttributeForm', $this, 'extend_attribute_form');
		 *    Backend::$events->addEvent('shop:onGetAttributeFieldOptions', $this, 'get_attribute_field_options');
		 * }
		 * 
		 * public function extend_attribute_model($attribute, $context)
		 * {
		 *    $attribute->define_column('x_drop_down', 'Some drop-down menu');
		 * }
		 * 
		 * public function extend_attribute_form($attribute, $context)
		 * {
		 *    $attribute->add_form_field('x_drop_down')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_attribute_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_drop_down')
		 *   {
		 *     $options = array(
		 *       0 => 'Option 1',
		 *       1 => 'Option 2',
		 *       2 => 'Option 3'
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
		 * @event shop:onGetAttributeFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendAttributeModel
		 * @see shop:onExtendAttributeForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetAttributeFieldOptions($db_name, $field_value) {}
	}

?>