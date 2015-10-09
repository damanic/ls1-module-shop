<?php

	/**
	 * Represents a custom product group.
	 * There is no a special CMS action in LemonStand for loading custom groups from the database. 
	 * To load a custom group with a specific API code use the following code:
	 * <pre>$group = Shop_CustomGroup::create()->find_by_code('featured');</pre>
	 * @property string $code Specifies the group API code.
	 * @property string $name Specifies the group name.
	 * @documentable
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_CustomGroup extends Db_ActiveRecord
	{
		public $table_name = 'shop_custom_group';
		protected $api_added_columns = array();

		protected static $product_sort_orders = null;

		public static function create()
		{
			return new self();
		}
		
		public $has_and_belongs_to_many = array(
			'all_products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_customgroups', 'primary_key'=>'shop_custom_group_id', 'foreign_key'=>'shop_product_id', 'order'=>'name'),
			
			// Interface products list
			//
			'products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_customgroups', 'primary_key'=>'shop_custom_group_id', 'foreign_key'=>'shop_product_id', 'order'=>'name', 'conditions'=>'((shop_products.enabled=1 and not (
			shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock=0))
		)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
			and not (
			grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock=0))
		))) and (shop_products.disable_completely is null or shop_products.disable_completely = 0)', 'order'=>'shop_products_customgroups.product_group_sort_order')
		);
		
		public $calculated_columns = array( 
			'product_num'=>array('sql'=>'select count(*) from shop_products,  shop_products_customgroups where
				shop_products.id=shop_products_customgroups.shop_product_id and
				shop_products_customgroups.shop_custom_group_id=shop_custom_group.id', 'type'=>db_number)
		);

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Group Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('code', 'Code')->validation()->fn('trim')->required()->unique();

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			
			$this->define_multi_relation_column('all_products', 'all_products', 'Products', $front_end ? null : '@name')->invisible()->validation();
			$this->define_column('product_num', 'Products');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomGroupModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->add_form_field('name')->tab('Group');
			$this->add_form_field('code')->tab('Group')->comment('You will use the code to refer the group to output its contents on pages.', 'above');
			
			$this->add_form_section('Manage the product group contents. You can manage the product order by dragging the arrow icons up and down.')->tab('Products');

			if (!$front_end)
				$this->add_form_field('all_products')->tab('Products')->comment('Products belonging to the group', 'above')->renderAs('products')->referenceSort('@name');
			Backend::$events->fireEvent('shop:onExtendCustomGroupForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomGroupFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function after_delete()
		{
			Db_DbHelper::query('delete from shop_products_customgroups where shop_custom_group_id=:id', array('id'=>$this->id));
		}
		
		public function get_products_orders()
		{
			if (self::$product_sort_orders !== null)
				return self::$product_sort_orders;
			
			$orders = Db_DbHelper::objectArray('select product_group_sort_order, shop_product_id from shop_products_customgroups where shop_custom_group_id=:group_id', 
			array('group_id'=>$this->id));
			
			$result = array();
			foreach ($orders as $order_item)
				$result[$order_item->shop_product_id] = $order_item->product_group_sort_order;

			return self::$product_sort_orders = $result;
		}
		
		public function set_product_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update shop_products_customgroups set product_group_sort_order=:product_group_sort_order where shop_product_id=:product_id and shop_custom_group_id=:group_id', array(
					'product_group_sort_order'=>$order,
					'product_id'=>$id,
					'group_id'=>$this->id
				));
			}
		}

		/**
		 * Returns a list of the group products.
		 * The result of this function is an object of the {@link Shop_Product} class. To obtain a collection of 
		 * products call the {@link Db_ActiveRecord::find_all() find_all()} method of the returned object:
		 * <pre>$full_product_list = $group->list_products()->find_all()</pre> 
		 * You can pass an array of options to the method parameter. The currently supported option is the <em>sorting</em>. 
		 * By default the product list is sorted by product name. You can sort product them by another field. 
		 * Also, you can sort the product list by multiple fields:
		 * <pre>
		 * $product_list = $group->list_products(array(
		 *   'sorting'=>array('price', 'name')
		 * ))
		 * </pre>
		 * The supported fields you can sort the products are:
		 * <ul>
		 *   <li><em>name</em> - sort the product list by name</li>
		 *   <li><em>price</em> - sort the product list by the base price</li>
		 *   <li><em>sku</em> - sort the product list by SKU</li>
		 *   <li><em>weight</em> - sort the product list by weight</li>
		 *   <li><em>width</em> - sort the product list by width</li>
		 *   <li><em>height</em> - sort the product list by height</li>
		 *   <li><em>depth</em> - sort the product list by depth</li>
		 *   <li><em>created_at</em> - sort the product list by the product creation date</li>
		 *   <li><em>rand()</em> - sort products randomly</li>
		 *   <li><em>manufacturer</em> -  sort products by the manufacturer name</li>
		 *   <li><em>expected_availability_date</em> - sort products by the availability date</li>
		 * </ul>
		 * You can add <em>desc</em> suffix to the sort field name to enable the descending sorting. For example, to sort the product list 
		 * by price in descending order, you can use the following code: 
		 * <pre>
		 * $product_list = $group->list_products(array(
		 *   'sorting'=>array('price desc')
		 * ));
		 * </pre>
		 * You can add custom sorting fields with {@link shop:onGetProductSortColumns} event.
		 * @documentable
		 * @param array $options Specifies the method options.
		 * @return Shop_Product Returns an object of the {@link Shop_Product} class. 
		 */
		public function list_products($options = array())
		{
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array();

			if (!is_array($sorting))
				$sorting = array();

			$allowed_sorting_columns = Shop_Product::list_allowed_sort_columns();

			$custom_sorting = false;

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, $allowed_sorting_columns))
					continue;

				$custom_sorting = true;

				if (strpos($sorting_column, 'price') !== false)
				{
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				}
				elseif(strpos($sorting_column, 'manufacturer') !== false)
					$sorting_column = str_replace('manufacturer', 'manufacturer_link_calculated', $sorting_column);
				elseif (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}

			$customer_group_id = Cms_Controller::get_customer_group_id();

			$product_obj = $this->products_list;
			
			if ($custom_sorting)
				$product_obj->reset_order();

			$product_obj->apply_customer_group_visibility();
			$product_obj->apply_catalog_visibility();
			
			$product_obj->where('
				((shop_products.enable_customer_group_filter is null or shop_products.enable_customer_group_filter=0) or (
					shop_products.enable_customer_group_filter = 1 and
					exists(select * from shop_products_customer_groups where shop_product_id=shop_products.id and customer_group_id=?)
				))
			', $customer_group_id);
			
			if ($custom_sorting)
			{
				$sort_str = implode(', ', $sorting);
				$product_obj->order($sort_str);
			}

			return $product_obj;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		/**
		 * Allows to define new columns in the custom product group model.
		 * To add new columns to the category model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the group object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_custom_group</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupModel', $this, 'extend_custom_group_model');
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupForm', $this, 'extend_custom_group_form');
		 * }
		 *     
		 * public function extend_custom_group_model($custom_group, $context)
		 * {
		 *   $custom_group->define_column('x_gender', 'Gender');
		 * }
		 *     
		 * public function extend_custom_group_form($custom_group, $context)
		 * {
		 *   $custom_group->add_form_field('x_gender')->tab('Group');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomGroupModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomGroupForm
		 * @see shop:onGetCustomGroupFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_CustomGroup $group Specifies the group object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCustomGroupModel($group, $context) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Product Group form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendCustomGroupModel} event. 
		 * To add new fields to the product group form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * group object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupModel', $this, 'extend_custom_group_model');
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupForm', $this, 'extend_custom_group_form');
		 * }
		 *     
		 * public function extend_custom_group_model($custom_group, $context)
		 * {
		 *   $custom_group->define_column('x_gender', 'Gender');
		 * }
		 *     
		 * public function extend_custom_group_form($custom_group, $context)
		 * {
		 *   $custom_group->add_form_field('x_gender')->tab('Group');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomGroupForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomGroupModel
		 * @see shop:onGetCustomGroupFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_CustomGroup $group Specifies the group object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCustomGroupForm($group, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendCustomGroupForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupModel', $this, 'extend_custom_group_model');
		 *   Backend::$events->addEvent('shop:onExtendCustomGroupForm', $this, 'extend_custom_group_form');
		 *   Backend::$events->addEvent('shop:onGetCustomGroupFieldOptions', $this, 'get_custom_group_options');
		 * }
		 *     
		 * public function extend_custom_group_model($custom_group, $context)
		 * {
		 *   $custom_group->define_column('x_gender', 'Gender');
		 * }
		 *     
		 * public function extend_custom_group_form($custom_group, $context)
		 * {
		 *   $custom_group->add_form_field('x_gender')->tab('Group')->renderAs(frm_dropdown);
		 * }
		 *     
		 * public function get_custom_group_options($field_name, $current_value)
		 * {
		 *   if ($field_name == 'x_gender')
		 *   {
		 *     $options = array('male'=>'Male', 'female'=>'Female', 'unisex'=>'Unisex');
		 *     if ($current_value == -1)
		 *       return $options;
		 *        
		 *     if (array_key_exists($current_value, $options))
		 *       return $options[$current_value];
		 *   }
		 * }
		 * </pre>
		 * @event shop:onGetCustomGroupFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomGroupModel
		 * @see shop:onExtendCustomGroupForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetCustomGroupFieldOptions($db_name, $field_value) {}
	}

?>