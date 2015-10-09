<?php

	/**
	 * Represents a {@link Shop_Product product} extra option.
	 * @property integer $id Specifies the extra option record identifier.
	 * @property string $description Specifies the extra option description.
	 * @property float $price Specifies the extra option price.
	 * @property string $group_name Specifies the extra option group name.
	 * @property Db_DataCollection $images A collection of the extra option images. 
	 * Each element in the collection is an object of {@link Db_File} class.
	 * @property float $weight Specifies the extra option weight.
	 * @property float $width Specifies the extra option width.
	 * @property float $height Specifies the extra option height.
	 * @property float $depth Specifies the extra option depth.
	 * @property string $option_key Specifies the extra option key.
	 * @documentable
	 * @see http://lemonstand.com/docs/displaying_product_extra_options Displaying product extra options
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ExtraOption extends Db_ActiveRecord
	{
		public $table_name = 'shop_extra_options';
		protected $api_added_columns = array();
		
		public $has_many = array(
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_ExtraOption' and field='images'", 'order'=>'sort_order, id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_column('description', 'Description')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('price', 'Price')->currency(true)->validation()->fn('trim')->required();
			$this->define_column('group_name', 'Group')->validation();

			if (!$front_end)
				$this->define_multi_relation_column('images', 'images', 'Images', '@name')->invisible();

			$this->define_column('weight', 'Weight')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('width', 'Width')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('height', 'Height')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('depth', 'Depth')->defaultInvisible()->validation()->fn('trim');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendExtraOptionModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('description')->comment('Specify the option description, e.g. "Gift wrap".', 'above')->size('small')->tab('Option');
			$this->add_form_field('price')->comment('Specify a value to be added to the product price if this option is selected.', 'above')->tab('Option');
			$this->add_form_field('group_name')->comment('You can group extras with equal group names.', 'above')->tab('Option')->renderAs(frm_dropdown);

			$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded')->fileDownloadBaseUrl(url('ls_backend/files/get/'));

			$this->add_form_field('weight', 'left')->tab('Shipping');
			$this->add_form_field('width', 'right')->tab('Shipping');
			$this->add_form_field('height', 'left')->tab('Shipping');
			$this->add_form_field('depth', 'right')->tab('Shipping');

			Backend::$events->fireEvent('shop:onExtendExtraOptionForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetExtraOptionFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_group_name_options($key = -1)
		{
			$result = array();
			$result[''] = '<group is not assigned>';
			$result[-1] = '<create new group>';
			if (strlen($this->group_name))
				$result[$this->group_name] = $this->group_name;
			
			$groups = self::get_group_names();
			foreach ($groups as $group)
			{
				if (!strlen($group))
					continue;

				$result[$group] = $group;
			}
			
			return $result;
		}

		public function copy()
		{
			$obj = new self();
			$obj->description = $this->description;
			$obj->price = $this->price;
			$obj->extra_option_sort_order = $this->extra_option_sort_order;
			$obj->group_name = $this->group_name;
			
			$images = $this->images;
			foreach ($obj->images as $existing_image)
				$existing_image->delete;
				
			foreach ($this->api_added_columns as $field)
				$obj->$field = $this->$field;

			foreach ($images as $image)
			{
				$image_copy = $image->copy();
				$image_copy->master_object_class = get_class($obj);
				$image_copy->field = $image->field;
				$image_copy->save();
				$obj->images->add($image_copy);
			}

			return $obj;
		}
		
		public function before_save($deferred_session_key = null) 
		{
			$this->option_key = md5($this->description);
		}

		/**
		 * Returns the extra option price taking into account the 
		 * {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ tax inclusive settings}. 
		 * Example:
		 * <pre>
		 * <? foreach ($product->extra_options as $option): ?>
		 *   <?= h($option->description) ?>: <?= format_currency($option->get_price($product)) ?>
		 * <? endforeach ?>
		 * </pre>
		 * @documentable
		 * @param Shop_Product $product Specifies a product the extra option belongs to.
		 * @param boolean $force_tax Forces the method to include tax into the result.
		 * @return float Returns the extra option price.
		 */
		public function get_price($product, $force_tax = false)
		{
			$price = $this->get_price_no_tax($product);
		
			$include_tax = $force_tax || Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns the extra option price without the tax included, regardless of the 
		 * {@link http://lemonstand.com/docs/configuring_lemonstand_for_tax_inclusive_environments/ tax configuration}.
		 * @documentable
		 * @param Shop_Product $product Specifies a product the extra option belongs to.
		 * @return float Returns the extra option price.
		 */
		public function get_price_no_tax($product)
		{
			$price = $this->price;
			
			$prices = Backend::$events->fireEvent('shop:onGetProductExtraPrice', $this, $product);

			foreach ($prices as $custom_price)
			{
				if (strlen($custom_price))
				{
					$price = $custom_price;
					break;
				}
			}
			
			return $price;
		}

		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			$result = -1;

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				if ($id == -1)
					$result = $order;

				Db_DbHelper::query('update shop_extra_options set extra_option_sort_order=:extra_option_sort_order where id=:id', array(
					'extra_option_sort_order'=>$order,
					'id'=>$id
				));
			}

			return $result;
		}
		
		public function after_create() 
		{
//			$this->option_key = md5($this->id);
			
			Db_DbHelper::query('update shop_extra_options set extra_option_sort_order=:extra_option_sort_order where id=:id', array(
				'extra_option_sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->extra_option_sort_order = $this->id;
		}
		
		/**
		 * Returns a list of extra option group names.
		 * @documentable
		 * @return array Returns an array of extra option group names.
		 */
		public static function get_group_names()
		{
			return Db_DbHelper::scalarArray('select distinct group_name from shop_extra_options order by group_name');
		}
		
		/**
		 * Finds an extra option belonging to a specific product.
		 * @documentable
		 * @param Shop_Product Specifies the product object.
		 * @param string $extra_key Specifies the extra option key.
		 * @return Shop_ExtraOption Returns the extra option object. Returns NULL if the extra option is not found.
		 */
		public static function find_product_extra_option($product, $extra_key)
		{
			$product_extras = $product->extra_options;
			foreach ($product_extras as $option)
			{
				if ($option->option_key == $extra_key)
					return $option;
			}
			
			return null;
		}
		
		/**
		 * Returns the extra option volume.
		 * @documentable 
		 * @return float Returns the extra option volume.
		 */
		public function volume()
		{
			return $this->width*$this->height*$this->depth;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public static function sort_extra_options_by_group($option1, $option2)
		{
			return strcmp($option1->group_name, $option2->group_name);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the extra option model.
		 * The event handler should accept a single parameter - the extra option object. 
		 * To add new columns to the extra option model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the extra option object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_extra_options</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionModel', $this, 'extend_extra_model');
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionForm', $this, 'extend_extra_form');
		 * }
		 *  
		 * public function extend_extra_model($model)
		 * {
		 *   $model->define_column('x_color', 'Color');
		 * }
		 *  
		 * public function extend_extra_form($model, $context)
		 * {
		 *   $model->add_form_field('x_color', 'Color')->tab('Option');
		 * }
		 * </pre>
		 * @event shop:onExtendExtraOptionModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendExtraOptionForm
		 * @see shop:onGetExtraOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ExtraOption $extra_option Specifies the extra option object.
		 */
		private function event_onExtendExtraOptionModel($extra_option) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Extra Option form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendExtraOptionModel} event. 
		 * To add new fields to the extra option form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * option object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionModel', $this, 'extend_extra_model');
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionForm', $this, 'extend_extra_form');
		 * }
		 *  
		 * public function extend_extra_model($model)
		 * {
		 *   $model->define_column('x_color', 'Color');
		 * }
		 *  
		 * public function extend_extra_form($model, $context)
		 * {
		 *   $model->add_form_field('x_color', 'Color')->tab('Option');
		 * }
		 * </pre>
		 * @event shop:onExtendExtraOptionForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendExtraOptionModel
		 * @see shop:onGetExtraOptionFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_ExtraOption $extra_option Specifies the extra option object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendExtraOptionForm($extra_option, $context) {}
		
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendExtraOptionForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionModel', $this, 'extend_extra_model');
		 *   Backend::$events->addEvent('shop:onExtendExtraOptionForm', $this, 'extend_extra_form');
		 *   Backend::$events->addEvent('shop:onGetExtraOptionFieldOptions', $this, 'get_extra_field_options');
		 * }
		 * 
		 * public function extend_extra_model($model)
		 * {
		 *   $model->define_column('x_custom_field', 'Custom field');
		 * }
		 * 
		 * public function extend_extra_form($model, $context)
		 * {
		 *   $model->add_form_field('x_custom_field', 'Color')->renderAs(frm_dropdown)->tab('Option');
		 * }
		 * 
		 * public function get_extra_field_options($field, $current_key_value=-1)
		 * {
		 *   if ($field_name == 'x_custom_field')
		 *   {
		 *     $options = array(
		 *       0 => 'Option 1',
		 *       1 => 'Option 2'
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
		 * @event shop:onGetExtraOptionFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendExtraOptionModel
		 * @see shop:onExtendExtraOptionForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetExtraOptionFieldOptions($db_name, $field_value) {}
		
		/**
		 * Allows to override a product's extra option price.
		 * The handler should return the new price, if applicable. Example:
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->addEvent('shop:onGetProductExtraPrice', $this, 'get_product_extra_price');
		 * }
		 * 
		 * public function get_product_extra_price($extra, $product) 
		 * {
		 *   return $extra->price * 1.10;
		 * }
		 * </pre>
		 * @event shop:onGetProductExtraPrice
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_ExtraOption $extra_option Specifies the extra option object.
		 * @param Shop_Product $product Specifies the product object.
		 * @return float Returns the updated extra option price.
		 */	
		private function event_onGetProductExtraPrice($extra_option, $product) {}
	}

?>