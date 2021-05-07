<?php

	/**
	 * Represents a {@link Shop_Product product} property.
	 * @property integer $id Specifies the property record identifier.
	 * @property string $name Specifies the property name.
	 * @property string $value Specifies the property value.
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductProperty extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_properties';

		public $implement = 'Db_Sortable';
		public $custom_columns = array('value_pickup'=>db_text);

		protected $_property_set_property = null;

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Property Name')->validation()->fn('trim')->required();
			$value_column = $this->define_column('value', 'Value');
			$this->define_column('api_code', 'API Code')->invisible();

			if($context == 'form') {
				$this->define_column( 'value_pickup', 'Selection' );
				if($this->get_property_set_property()){
					$psp = $this->get_property_set_property();
					if($psp->required){
						$value_column->validation()->fn('trim')->required();
					}
				}
			}

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendAttributeModel', $this, $context);//@deprecated
			Backend::$events->fireEvent('shop:onExtendProductPropertyModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{

			$value_pickup_comment = 'Please enter a value to the text field below, or choose an existing value.';
			$prop_locked = false;
			$prop_comment = null;
			$select_values = array();

			$property_settings = $this->get_property_set_property();
			if($property_settings){
				$prop_locked = true;
				$select_values = $property_settings->get_select_values();
				if($select_values){
					$value_pickup_comment = 'Please select from one of the pre-defined options available';
				}
				if($property_settings->comment){
					$prop_comment = $property_settings->comment;
				}
			}

			$name_field = $this->add_form_field('name');
			$name_field->comment($prop_comment);
			if($prop_locked){
				$name_field->disabled();
			}
			$this->add_form_field('value_pickup')->renderAs(frm_dropdown)->emptyOption('<known property values>')->comment($value_pickup_comment, 'above')->cssClassName('relative');
			if(!$select_values){
				$value_field = $this->add_form_field( 'value' );
				$value_field->renderAs( frm_textarea )->size( 'small' )->cssClassName( 'relative' );
			}

			Backend::$events->fireEvent('shop:onExtendAttributeForm', $this, $context);//@deprecated
			Backend::$events->fireEvent('shop:onExtendProductPropertyForm', $this, $context);

			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}

		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$dep_result = Backend::$events->fireEvent('shop:onGetAttributeFieldOptions', $db_name, $current_key_value); //@deprecated
			$result = Backend::$events->fireEvent('shop:onGetProductPropertyFieldOptions', $db_name, $current_key_value);
			$result = array_merge($dep_result,$result);
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
			$obj->api_code = $this->api_code;
			
			return $obj;
		}
		
		public function get_value_pickup_options($key = -1)
		{
			$result = array();
			$values = array();


			$psp = $this->get_property_set_property();
			if($psp){
				$values = $psp->get_select_values();
			}

			if(empty($values)) {
				$name   = mb_strtolower( trim( $this->name ) );
				$values = Db_DbHelper::objectArray( 'select distinct id, value from shop_product_properties where lower(name)=:name group by value order by value', array( 'name' => $name ) );
			}

			foreach ($values as $key => $value)
			{
				$value = is_object($value) ? Phpr_Html::strTrim(str_replace("\n", " ", $value->value), 40) : $value;
				$result[$value] = $value;
			}
			
			return $result;
		}

		public function get_property_set_property(){
			if($this->_property_set_property){
				return $this->_property_set_property;
			}
			if($this->property_set_property_id){
				return $this->_property_set_property = Shop_PropertySetProperty::create()->find($this->property_set_property_id);
			}
			return null;
		}
		
		public static function list_unique_names()
		{
			return Db_DbHelper::scalarArray('select distinct name from shop_product_properties');
		}
		
		public function load_value( $property_id)
		{
			if (!strlen($property_id))
				return;
				
			$this->value = Db_DbHelper::scalar('select value from shop_product_properties where id=:id', array('id'=>$property_id));
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
		 * Allows to define new columns in the product property model.
		 * The event handler should accept two parameters - the product property object and the form
		 * execution context string. To add new columns to the property model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the property object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_product_properties</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyModel', $this, 'extend_property_model');
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyForm', $this, 'extend_property_form');
		 * }
		 * 
		 * public function extend_property_model($property, $context)
		 * {
		 *    $property->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_property_form($property, $context)
		 * {
		 *    $property->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendProductPropertyModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductPropertyForm
		 * @see shop:onGetProductPropertyFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductProperty $property Specifies the product property object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductPropertyModel($property, $context) {}

		/**
		 * Allows to add new fields to the Add/Edit property section of the Add/Edit Product form in the Administration Area.
		 * Usually this event is used together with the {@link shop:onExtendProductPropertyModel} event.
		 * To add new fields to the property form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * property object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyModel', $this, 'extend_property_model');
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyForm', $this, 'extend_property_form');
		 * }
		 * 
		 * public function extend_property_model($property, $context)
		 * {
		 *    $property->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_property_form($property, $context)
		 * {
		 *    $property->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendProductPropertyForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductPropertyModel
		 * @see shop:onGetProductPropertyFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductProperty $property Specifies the product property object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductPropertyForm($property, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendProductPropertyForm} event.
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
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyModel', $this, 'extend_property_model');
		 *    Backend::$events->addEvent('shop:onExtendProductPropertyForm', $this, 'extend_property_form');
		 *    Backend::$events->addEvent('shop:onGetProductPropertyFieldOptions', $this, 'get_property_field_options');
		 * }
		 * 
		 * public function extend_property_model($property, $context)
		 * {
		 *    $property->define_column('x_drop_down', 'Some drop-down menu');
		 * }
		 * 
		 * public function extend_property_form($property, $context)
		 * {
		 *    $property->add_form_field('x_drop_down')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_property_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetProductPropertyFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductPropertyModel
		 * @see shop:onExtendProductPropertyForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetProductPropertyFieldOptions($db_name, $field_value) {}
	}

?>