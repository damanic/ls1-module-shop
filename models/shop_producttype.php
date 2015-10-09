<?php

	/**
	 * Represents a product type.
	 * By default LemonStand creates a few predefined product types: <em>Downloadable</em>, <em>Goods</em>, <em>Service</em>.
	 * Objects of this method are available through {@link Shop_Product::$product_type} property.
	 * @documentable
	 * @property string $name Specifies the product type name.
	 * @property string $code Specifies the product type API code.
	 * @property boolean $is_default Determines whether the product type is default for new products.
	 * @property boolean $files Determines whether products of this type support files.
	 * @property boolean $inventory Determines whether products of this type support inventory tracking.
	 * @property boolean $shipping Determines whether products of this type support shipping.
	 * @property boolean $grouped Determines whether products of this type support grouped products.
	 * @property boolean $options Determines whether products of this type support product options.
	 * @property boolean $extras Determines whether products of this type support product options.
	 * @see Shop_Product
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductType extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_types';
		protected $api_added_columns = array();
		
		public static function create()
		{
			return new self();
		}
		
		public static function get_default_type()
		{
			if ($default = self::create()->where('is_default=1')->find())
				return $default;
			else
				return self::create()->find();
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('name', 'Type Name')->order('asc')->validation()->fn('trim')->required('Please specify the product type name.');
			$this->define_column('code', 'API Code')->validation()->fn('trim')->unique('API code is already in use by another product type.');
			$this->define_column('is_default', 'Is default');
			$this->define_column('files', 'Enable files')->defaultInvisible();
			$this->define_column('inventory', 'Enable inventory tracking')->defaultInvisible();
			$this->define_column('shipping', 'Enable shipping')->defaultInvisible();
			if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
				$this->define_column('grouped', 'Enable grouped products')->defaultInvisible();
			$this->define_column('options', 'Enable options')->defaultInvisible();
			$this->define_column('extras', 'Enable extra options')->defaultInvisible();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductTypeModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name', 'left')->tab('Product Type');
			$this->add_form_field('code', 'right')->tab('Product Type');
			$this->add_form_field('is_default')->tab('Product Type')->renderAs('checkbox')->comment('Use this checkbox if you want this product type to be applied to all new products by default.');
			$this->add_form_field('files')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Files tab on the product page.');
			$this->add_form_field('inventory')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Inventory tab on the product page.');
			$this->add_form_field('shipping')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Shipping tab on the product page.');
			
			if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS'))
				$this->add_form_field('grouped')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Grouped tab on the product page.');
				
			$this->add_form_field('options')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Options tab on the product page.');
			$this->add_form_field('extras')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Extras tab on the product page.');
			
			Backend::$events->fireEvent('shop:onExtendProductTypeForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductTypeFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}

		public function before_delete($id = null)
		{
			//Do not allow deleting a product type if there are products assigned to it
			if ($products_num = Db_DbHelper::scalar('select count(*) from shop_products where product_type_id=:id', array('id'=>$this->id)))
				throw new Phpr_ApplicationException("Cannot delete this product type. There are $products_num product(s) belonging to it.");
			//Ensure there is at least one product type available at all times
			$count_types = Db_DbHelper::scalar('select count(*) from shop_product_types');
			if($count_types < 2)
				throw new Phpr_ApplicationException("Product type cannot be deleted because it is the only one configured. There should always be at least one product type configured.");
		}

		public function after_save()
		{
			//If the saved product type is now the default, make others not default
			if ($this->is_default)
				Db_DbHelper::query('update shop_product_types set is_default=0 where id<>:id', array('id'=>$this->id));
		}

		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the product type model.
		 * The event handler should accept a single parameter - the product type object. To add new columns to the product type model, 
		 * call the {@link Db_ActiveRecord::define_column() define_column()} method of the product type object. Before you add new columns to the model, 
		 * you should add them to the database (the <em>shop_product_types</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductTypeModel', $this, 'extend_product_type_model');
		 *   Backend::$events->addEvent('shop:onExtendProductTypeForm', $this, 'extend_product_type_model');
		 * }
		 * 
		 * public function extend_product_type_model($product_type)
		 * {
		 *   $product_type->define_column('x_custom_column', 'A custom column');
		 * }
		 *      
		 * public function extend_product_type_form($product_type, $context)
		 * {
		 *   $product_type->add_form_field('x_custom_column')->tab('Product Type');
		 * }
		 * </pre>
		 * @event shop:onExtendProductTypeModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductTypeForm
		 * @see shop:onGetProductTypeFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables.
		 * @param Shop_ProductType $product_type Specifies the product type object.
		 */
		private function event_onExtendProductTypeModel($product_type) {}

		/**
		 * Allows to add new fields to the Create/Edit Product Type form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendProductTypeModel} event. 
		 * To add new fields to the product type form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * product type object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendProductTypeModel', $this, 'extend_product_type_model');
		 *   Backend::$events->addEvent('shop:onExtendProductTypeForm', $this, 'extend_product_type_model');
		 * }
		 * 
		 * public function extend_product_type_model($product_type)
		 * {
		 *   $product_type->define_column('x_custom_column', 'A custom column');
		 * }
		 *      
		 * public function extend_product_type_form($product_type, $context)
		 * {
		 *   $product_type->add_form_field('x_custom_column')->tab('Product Type');
		 * }
		 * </pre>
		 * @event shop:onExtendProductTypeForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductTypeModel
		 * @see shop:onGetProductTypeFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ProductType $product_type Specifies the product type object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendProductTypeForm($product_type, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendProductTypeForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendProductTypeModel', $this, 'extend_product_type_model');
		 *   Backend::$events->addEvent('shop:onExtendProductTypeForm', $this, 'extend_product_type_form');
		 *   Backend::$events->addEvent('shop:onGetProductTypeFieldOptions', $this, 'get_product_type_field_options');
		 * }
		 * 
		 * public function extend_product_type_model($product_type)
		 * {
		 *   $product_type->define_column('x_color', 'Color');
		 * }
		 *      
		 * public function extend_product_type_form($product_type, $context)
		 * {
		 *   $product_type->add_form_field('x_color')->tab('Product Type')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_product_type_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetProductTypeFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendProductTypeModel
		 * @see shop:onExtendProductTypeForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetProductTypeFieldOptions($db_name, $field_value) {}
	}
?>