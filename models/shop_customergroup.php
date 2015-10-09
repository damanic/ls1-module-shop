<?php

	/**
	 * Represents a customer group.
	 * @property integer $id Specifies the customer group record identifier.
	 * @property string $code Specifies the customer group API code.
	 * @property string $name Specifies the customer group name.
	 * @property string $description Specifies the customer group description.
	 * @property boolean $disable_tax_included Indicates whether taxes should not be included to the displayed product prices for customers belonging to this group.
	 * @documentable
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_CustomerGroup extends Db_ActiveRecord
	{
		const guest_group = 'guest';
		const registered_group = 'registered';
		
		public $table_name = 'shop_customer_groups';
		protected $api_added_columns = array();

		protected static $guest_group = null;
		protected static $cache = null;

		public $calculated_columns = array(
			'customer_num'=>array('sql'=>"(select count(*) from shop_customers where customer_group_id=shop_customer_groups.id)", 'type'=>db_number)
		);
		
		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the group name");
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('customer_num', 'Customers')->validation()->fn('trim');
			
			$this->define_column('disable_tax_included', 'Do not include tax into displayed product prices')->listTitle('Disable Tax Inclusive');

			$this->define_column('code', 'API Code')->validation()->fn('trim')->fn('mb_strtolower')->unique('The API Code "%s" is already in use.');
			$this->define_column('tax_exempt', 'Tax Exempt');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomerGroupModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name');
			$this->add_form_field('description');
			$this->add_form_field('disable_tax_included')->comment('Use this checkbox if you want to override the global "Display catalog/cart prices including tax" option for customers belonging to this customer group.');
			$this->add_form_field('tax_exempt')->comment('Use this feature if the tax should not be applied to customers from this group.');
			
			$field = $this->add_form_field('code')->comment('You can use the API code for referring the customer group in the API calls.', 'above');
			if ($this->code == self::guest_group || $this->code == self::registered_group)
				$field->disabled = true;
				
			Backend::$events->fireEvent('shop:onExtendCustomerGroupForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomerGroupFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}

			return false;
		}

		public function before_delete($id=null)
		{
			if ($this->code == self::guest_group || $this->code == self::registered_group)
				throw new Phpr_ApplicationException("The Guest and Registered customer groups cannot be deleted.");
			
			if ($this->customer_num)
				throw new Phpr_ApplicationException("The group cannot be deleted because {$this->customer_num} customer(s) belong to this group.");
		}
		
		/**
		 * Returns the Guest customer group.
		 * @documentable
		 * @return Shop_CustomerGroup Returns the Guest customer group object.
		 */
		public static function get_guest_group()
		{
			if (self::$guest_group !== null)
				return self::$guest_group;
				
			self::$guest_group = self::create()->where('code = ?', self::guest_group)->find();
			
			if (!self::$guest_group)
				throw new Phpr_ApplicationException("The Guest customer group is not found in the database.");
			
			return self::$guest_group;
		}
		
		/**
		 * Returns a list of customer groups by their API codes. 
		 * Example:
		 * <pre>$groups = Shop_CustomerGroup::list_groups_by_codes(array('registered', 'reseller'));</pre>
		 * @documentable 
		 * @param array $codes Specifies a list of customer group API codes.
		 * @return Db_DataCollection Returns a collection of customer group objects ({@link Shop_CustomerGroup}).
		 */
		public static function list_groups_by_codes($codes)
		{
			foreach ($codes as &$code)
				$code = mb_strtolower($code);
				
			if (!is_array($codes))
				$codes = array($codes);

			if (!count($codes))
				return new Db_DataCollection();

			return self::create()->where('code in (?)', array($codes))->find_all();
		}
		
		/**
		 * Returns a list of a all customer groups.
		 * @documentable
		 * @return array Returns an array of {@link Shop_CustomerGroup} objects.
		 */
		public static function list_groups()
		{
			if (self::$cache === null)
				self::$cache = self::create()->find_all()->as_array(null, 'id');
				
			return self::$cache;
		}
		
		/**
		 * Returns a customer group by its identifier.
		 * @documentable
		 * @param integer $id Specifies the group identifier.
		 * @return Shop_CustomerGroup Returns the customer group object. Returns NULL if the customer group is not found.
		 */
		public static function find_by_id($id)
		{
			$groups = self::list_groups();
			if (array_key_exists($id, $groups))
				return $groups[$id];
				
			return null;
		}
		
		/**
		 * Returns a customer group by its API code.
		 * @documentable
		 * @param string $code Specifies the group API code.
		 * @return Shop_CustomerGroup Returns the customer group object. Returns NULL if the customer group is not found.
		 */
		public static function find_by_code($code)
		{
			$groups = self::list_groups();
			foreach ($groups as $group)
				if ($group->code === $code)
					return $group;
					
			return null;
		}
		
		public static function is_tax_exempt($id)
		{
			$group = self::find_by_id($id);
			if (!$group)
				return false;
				
			return $group->tax_exempt;
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the customer group model.
		 * The event handler should accept a single parameter - the customer group object. 
		 * To add new columns to the customer group model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the group object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_customer_groups</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupModel', $this, 'extend_customer_group_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupForm', $this, 'extend_customer_group_form');
		 * }
		 * 
		 * public function extend_customer_group_model($customer_group, $context)
		 * {
		 *    $customer_group->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_customer_group_form($customer_group, $context)
		 * {
		 *    $customer_group->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomerGroupModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerGroupForm
		 * @see shop:onGetCustomerGroupFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_CustomerGroup $group Specifies the customer group object.
		 */
		private function event_onExtendCustomerGroupModel($group) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Customer Group form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendCustomerGroupModel} event. 
		 * To add new fields to the customer group form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * customer group object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupModel', $this, 'extend_customer_group_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupForm', $this, 'extend_customer_group_form');
		 * }
		 * 
		 * public function extend_customer_group_model($customer_group, $context)
		 * {
		 *    $customer_group->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_customer_group_form($customer_group, $context)
		 * {
		 *    $customer_group->add_form_field('x_extra_description');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomerGroupForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerGroupModel
		 * @see shop:onGetCustomerGroupFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Customer $customer Specifies the customer object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCustomerGroupForm($customer, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendCustomerGroupForm} event.
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
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupModel', $this, 'extend_customer_group_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerGroupForm', $this, 'extend_customer_group_form');
		 *    Backend::$events->addEvent('shop:onGetCustomerGroupFieldOptions', $this, 'get_customer_group_field_options');
		 * }
		 * 
		 * public function extend_customer_group_model($customer_group, $context)
		 * {
		 *    $customer_group->define_column('x_drop_down', 'Some drop-down menu');
		 * }
		 * 
		 * public function extend_customer_group_form($customer_group, $context)
		 * {
		 *    $customer->add_form_field('x_drop_down')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_customer_group_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetCustomerGroupFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerGroupModel
		 * @see shop:onExtendCustomerGroupForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetCustomerGroupFieldOptions($db_name, $field_value) {}
	}

?>