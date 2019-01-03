<?php

	/**
	 * Represents a customer.
	 * @property integer $id Specifies the customer record identifier.
	 * @property Phpr_DateTime $created_at Specifies the date and time when the customer record was created.
	 * @property Phpr_DateTime $updated_at Specifies the date and time when the customer record was updated last time.
	 * @property string $first_name Specifies the customer first name.
	 * @property string $last_name Specifies the customer last name.
	 * @property string $email Specifies the customer email.
	 * @property string $company Specifies the customer company name.
	 * @property string $phone Specifies the customer phone number.
	 * @property Shop_CountryState $billing_state Specifies a billing state.
	 * @property Shop_Country $billing_country Specifies a billing country.
	 * @property string $billing_street_addr Specifies the customer billing street address.
	 * @property string $billing_city Specifies the customer billing city.
	 * @property string $billing_zip Specifies the customer billing ZIP/postal code.
	 * @property string $shipping_first_name Specifies the shipping first name.
	 * @property string $shipping_last_name Specifies the shipping last name.
	 * @property string $shipping_company Specifies the shipping company name.
	 * @property string $shipping_phone Specifies the shipping phone number.
	 * @property Shop_CountryState $shipping_state Specifies a shipping state.
	 * @property Shop_Country $shipping_country Specifies a shipping country.
	 * @property string $shipping_street_addr Specifies the customer shipping street address.
	 * @property string $shipping_city Specifies the customer shipping city.
	 * @property string $shipping_zip Specifies the customer shipping ZIP/postal code.
	 * @property boolean $guest Indicates whether the customer is a guest.
	 * @property Db_DataCollection $orders A list of customer orders. 
	 * Each element of the collection is an object of the {@link Shop_Order} class.
	 * @property string $notes Specifies the customer notes.
	 * @property Shop_CustomerGroup $group A reference to a customer group the customer belongs to.
	 * @property Db_DataCollection $image The customer photo. The collection contains zero or one object of {@link Db_File} class.
	 * @documentable
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_Customer extends Db_ActiveRecord
	{
		public $table_name = 'shop_customers';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		public $auto_footprints_default_invisible = true;

		public $custom_columns = array('password_confirm'=>db_varchar, 'full_name'=>db_varchar);

		public $belongs_to = array(
			'shipping_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'shipping_country_id'),
			'billing_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'billing_country_id'),
			
			'shipping_state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'shipping_state_id'),
			'billing_state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'billing_state_id'),
			
			'group'=>array('class_name'=>'Shop_CustomerGroup', 'foreign_key'=>'customer_group_id'),
		);
		
		public $has_many = array(
			'orders'=>array('class_name'=>'Shop_Order', 'foreign_key'=>'customer_id', 'order'=>'order_datetime desc'),
    		'image'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Customer' and field='image'", 'order'=>'sort_order, id', 'delete'=>true)
		);
		
		protected $plain_password;
		protected $product_quantity_cache = array();
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('first_name', 'First Name')->order('asc')->validation()->fn('trim')->required("Please specify a first name");
			$this->define_column('last_name', 'Last Name')->validation()->fn('trim')->required("Please specify a last name");
			$this->define_column('email', 'Email')->validation()->fn('trim')->fn('mb_strtolower')->required()->Email('Please provide valid email address.')->method('validate_email');
			$this->define_column('company', 'Company')->validation()->fn('trim');
			$this->define_column('phone', 'Phone')->validation()->fn('trim');
			$this->define_column('password', 'Password')->invisible()->validation()->fn('trim');
			$this->define_column('password_confirm', 'Password Confirmation')->invisible()->validation()->matches('password', 'Password and confirmation password do not match.');
			$this->define_column('guest', 'Guest');
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_relation_column('group', 'group', 'Customer Group ', db_varchar, $front_end ? null : '@name');
			$this->define_relation_column('billing_country', 'billing_country', 'Country ', db_varchar, $front_end ? null : '@name')->listTitle('Bl. Country')->defaultInvisible();
			$this->define_relation_column('billing_state', 'billing_state', 'State ', db_varchar, $front_end ? null : '@name')->listTitle('Bl. State')->defaultInvisible();
			
			$this->define_column('billing_street_addr', 'Street Address')->listTitle('Bl. Address')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('billing_city', 'City')->listTitle('Bl. City')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('billing_zip', 'Zip/Postal Code')->listTitle('Bl. Zip/Postal Code')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('shipping_first_name', 'First Name')->listTitle('Sh. First Name')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_last_name', 'Last Name')->listTitle('Sh. Last Name')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_company', 'Company')->listTitle('Sh. Company')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_phone', 'Phone')->listTitle('Sh. Phone')->defaultInvisible()->validation()->fn('trim');

			$this->define_relation_column('shipping_country', 'shipping_country', 'Country ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->listTitle('Sh. Country');
			$this->define_relation_column('shipping_state', 'shipping_state', 'State ', db_varchar, $front_end ? null : '@name')->listTitle('Sh. State')->defaultInvisible();

			$this->define_column('shipping_street_addr', 'Street Address')->listTitle('Sh. Address')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_city', 'City')->listTitle('Sh. City')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_zip', 'Zip/Postal Code')->listTitle('Sh. Zip/Postal Code')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('shipping_addr_is_business', 'Business address')->invisible();

			$this->define_column('deleted_at', 'Deleted')->defaultInvisible()->dateFormat('%x %H:%M');
			$this->define_column('notes', 'Notes')->listTitle('Notes')->defaultInvisible();

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomerModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->add_form_field('first_name', 'left')->tab('Customer');
			$this->add_form_field('last_name', 'right')->tab('Customer');
			$this->add_form_field('email')->tab('Customer');
			$this->add_form_field('company', 'left')->tab('Customer');
			$this->add_form_field('phone', 'right')->tab('Customer');
			$this->add_form_field('password', 'left')->tab('Customer')->renderAs(frm_password)->noPreview();
			$this->add_form_field('password_confirm', 'right')->tab('Customer')->renderAs(frm_password)->noPreview();

			if (!$this->guest && !$front_end)
				$this->add_form_field('group')->tab('Customer');

			if (!$front_end)
			{
				$country_field = $this->add_form_field('billing_country', 'left')->tab('Billing Address');
				if ($context != 'preview')
					$country_field->renderAs('country');

				$this->add_form_field('billing_state', 'right')->tab('Billing Address');
			}
			
			$this->add_form_field('billing_street_addr')->tab('Billing Address')->nl2br(true)->renderAs(frm_textarea)->size('small');
			$this->add_form_field('billing_city', 'left')->tab('Billing Address');
			$this->add_form_field('billing_zip', 'right')->tab('Billing Address');

			if ($context != 'preview')
				$this->add_form_custom_area('copy_shipping_address')->tab('Shipping Address');

			$this->add_form_field('shipping_first_name', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_last_name', 'right')->tab('Shipping Address');

			$this->add_form_field('shipping_company', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_phone', 'right')->tab('Shipping Address');
			
			if ($context != 'preview' || $this->shipping_addr_is_business)
				$this->add_form_field('shipping_addr_is_business')->tab('Shipping Address');

			if (!$front_end)
			{
				$country_field = $this->add_form_field('shipping_country', 'left')->tab('Shipping Address');
				if ($context != 'preview')
					$country_field->renderAs('country');

				$this->add_form_field('shipping_state', 'right')->tab('Shipping Address');
			}

			$this->add_form_field('shipping_street_addr')->tab('Shipping Address')->nl2br(true)->renderAs(frm_textarea)->size('small');
			$this->add_form_field('shipping_city', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_zip', 'right')->tab('Shipping Address');
			
			if (!$front_end && $context != 'preview')
				$this->add_form_field('notes')->tab('Notes')->noLabel();
			else if (!$front_end)
				$this->add_form_field('notes')->tab('Customer');
			
			Backend::$events->fireEvent('shop:onExtendCustomerForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
				{
					$form_field->optionsMethod('get_added_field_options');
					$form_field->optionStateMethod('get_added_field_option_state');
				}
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomerFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || ($options !== false && $current_key_value != -1))
					return $options;
			}
			
			return false;
		}

		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomerFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function validate_email($name, $value)
		{
			if ($this->guest)
				return true;

			$value = trim(strtolower($value));
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('email=?', $value);
			if ($this->id)
				$customer->where('shop_customers.id <> ?', $this->id);

			$customer = $customer->find();

			if ($customer)
				$this->validation->setError("Email ".$value." is already in use. Please specify another email address.", $name, true);
			
			return true;
		}

		/**
		 * Finds a registered customer by its email address.
		 * @documentable
		 * @param string $email Specifies the customer's email address.
		 * @return Shop_Customer Returns a customer object or NULL of the customer is not found.
		 */
		public static function find_registered_by_email($email)
		{
			$value = trim(strtolower($email));
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('email=?', $value);

			return $customer->find();
		}
		
		public function get_group_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CustomerGroup::create()->find($key_value);
				return $obj ? $obj->name : null;
			}

			$options = array(null=>'<please select>');
			$groups = Shop_CustomerGroup::create()->where('(shop_customer_groups.code is null or shop_customer_groups.code<>?)', Shop_CustomerGroup::guest_group)->order('name')->find_all();
			$groups_array = $groups->as_array('name', 'id');
			foreach($groups_array as $id => $name){
				$options[$id] = $name;
			}
			return $options;
		}
		
		public function get_shipping_country_options($key_value=-1)
		{
			return $this->list_countries($key_value, $this->shipping_country_id);
		}
		
		public function get_billing_country_options($key_value=-1)
		{
			return $this->list_countries($key_value, $this->billing_country_id);
		}
		
		protected function list_countries($key_value=-1, $default = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_Country::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 or id=:id order by name', array('id'=>$default));
			$result = array(null=>'<please select>');
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		public function get_shipping_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			return $this->list_states($this->shipping_country_id);
		}
		
		public function get_billing_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;
					
				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}

			return $this->list_states($this->billing_country_id);
		}
		
		public function list_states($country_id)
		{
			if (!$country_id || !Shop_Country::create()->find($country_id))
			{
				$obj = Shop_Country::create()->order('name')->find();
				if ($obj)
					$country_id = $obj->id;
			}

			$states = Db_DbHelper::objectArray(
				'select * from shop_states where country_id=:country_id order by name',
				array('country_id'=>$country_id)
			);
			
			if (!count($states))
				$result = array(null=>'<no states available>');

			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->plain_password = $this->password;

			if (!$this->guest)
			{
				if (!strlen($this->password))
				{
					if ($this->is_new_record())
						$this->validation->setError('Please provide a password.', 'password', true);
					else
						$this->password = $this->fetched['password'];
				} else
					$this->password = Phpr_SecurityFramework::create()->salted_hash($this->password);
			}
			if (!$this->customer_group_id)
			{
				if ($this->guest) {
					$group = Shop_CustomerGroup::create()->find_by_code(Shop_CustomerGroup::guest_group);
					if ($group)
						$this->customer_group_id = $group->id;
				} else {
					$group = Shop_CustomerGroup::create()->find_by_code(Shop_CustomerGroup::registered_group);
					if ($group)
						$this->customer_group_id = $group->id;
				}
			}
		}

		public function __get($name)
		{
			if ($name == 'name')
				return $this->first_name.' '.$this->last_name;
			
			return parent::__get($name);
		}

		/**
		 * Copies the customer name and address information to an order object.
		 * The method doesn't save the order object.
		 * @documentable
		 * @param Shop_Order Order Specifies an order object to copy data to.
		 */
		public function copy_to_order($order)
		{
			$order->billing_first_name = $this->first_name;
			$order->billing_last_name = $this->last_name;
			$order->billing_email = $this->email;
			$order->billing_phone = $this->phone;
			$order->billing_company = $this->company;
			$order->billing_street_addr = $this->billing_street_addr;
			$order->billing_city = $this->billing_city;
			$order->billing_state_id = $this->billing_state_id;
			$order->billing_zip = $this->billing_zip;
			$order->billing_country_id = $this->billing_country_id;
			
			$order->shipping_first_name = $this->shipping_first_name;
			$order->shipping_last_name = $this->shipping_last_name;
			$order->shipping_phone = $this->shipping_phone;
			$order->shipping_company = $this->shipping_company;
			$order->shipping_street_addr = $this->shipping_street_addr;
			$order->shipping_city = $this->shipping_city;
			$order->shipping_state_id = $this->shipping_state_id;
			$order->shipping_zip = $this->shipping_zip;
			$order->shipping_country_id = $this->shipping_country_id;
			$order->shipping_addr_is_business = $this->shipping_addr_is_business;
		}
		
		/**
		 * Copies the customer name and address information from an order object.
		 * The method doesn't save the customer object.
		 * @documentable
		 * @param Shop_Order Order Specifies an order object to copy data from.
		 */
		public function copy_from_order($order)
		{
			$this->first_name = $order->billing_first_name;
			$this->last_name = $order->billing_last_name;
			$this->email = $order->billing_email;
			$this->phone = $order->billing_phone;
			$this->company = $order->billing_company;
			$this->billing_street_addr = $order->billing_street_addr;
			$this->billing_city = $order->billing_city;
			$this->billing_state_id = $order->billing_state_id;
			$this->billing_zip = $order->billing_zip;
			$this->billing_country_id = $order->billing_country_id;
			
			$this->shipping_first_name = $order->shipping_first_name;
			$this->shipping_last_name = $order->shipping_last_name;
			$this->shipping_phone = $order->shipping_phone;
			$this->shipping_company = $order->shipping_company;
			$this->shipping_street_addr = $order->shipping_street_addr;
			$this->shipping_city = $order->shipping_city;
			$this->shipping_state_id = $order->shipping_state_id;
			$this->shipping_zip = $order->shipping_zip;
			$this->shipping_country_id = $order->shipping_country_id;
			$this->shipping_addr_is_business = $order->shipping_addr_is_business;
		}

		/**
		 * Deletes a customer from the database or marks the customer as deleted.
		 * If a customer has any orders associated, it cannot be deleted permanently. 
		 * The method marks such customers as deleted. Deleted customers can be restored
		 * with {@link Shop_Customer::restore_customer() restore_customer()} method.
		 * @documentable
		 */
		public function delete_customer()
		{
			if ($this->orders->count)
			{
				if ($this->deleted_at)
					return false;
				
				$this->deleted_at = Phpr_DateTime::now();
				Db_DbHelper::query(
					'update shop_customers set deleted_at=:deleted_at where id=:id', array(
						'deleted_at'=>$this->deleted_at,
						'id'=>$this->id
					)
				);
				
				return false;
			}
			
			$this->delete();
			return true;
		}
		
		/**
		 * Restores a customer previously deleted with {@link Shop_Customer::delete_customer() delete_customer()} method.
		 * @documentable
		 */
		public function restore_customer()
		{
			$this->deleted_at = null;

			Db_DbHelper::query(
				'update shop_customers set deleted_at=:deleted_at where id=:id', array(
					'deleted_at'=>$this->deleted_at,
					'id'=>$this->id
				)
			);
		}

		public function before_delete($id=null) 
		{
			if ($order_num = $this->orders->count)
				throw new Phpr_ApplicationException("Error deleting customer. There are $order_num order(s) belonging to this customer.");
		}

		public function generate_password()
		{
			$password = null;
			
			$event_result = Backend::$events->fireEvent('shop:onBeforeGenerateCustomerPassword');
			foreach ($event_result as $event_password)
			{
				if ($event_password) 
				{
					$password = $event_password;
					break;
				}
			}
			
			if (!strlen($password))
			{
				$letters = 'abcdefghijklmnopqrstuvwxyz';
				$password = null;
				for ($i = 1; $i <= 6; $i++)
					$password .= $letters[rand(0,25)];
			}
			
			$this->password_confirm = $password;
			$this->password = $password;
		}

		public function generate_password_restore()
		{
			$hash = Phpr_SecurityFramework::create()->salted_hash(rand(1,400));
			while ($count = Db_DbHelper::scalar('select count(*) from shop_customers where password_restore_hash = :hash', array('hash' => $hash)))
				$hash = Phpr_SecurityFramework::create()->salted_hash(rand(1,400));
			$this->password_restore_hash = $hash;

			$this->password_restore_time = Phpr_DateTime::now();
			return $hash;;
		}

		/**
		* Finds a customer with the specified password reset hash
		* @param string $hash The password reset hash
		* @return mixed Shop_Customer object if customer with specified hash is found and hash is still valid (generated under 24 hours ago) or null if customer not found
		 */
		public static function get_from_password_reset_hash($hash)
		{
			if(!strlen($hash))
				return null;

			$now = Phpr_DateTime::now();
			$yesterday = $now->addDays(-1);

			$customer = Shop_Customer::create()->where('password_restore_hash=?', $hash)->where('password_restore_time>?', $yesterday)->find(null, array(), 'front_end');
			return $customer;
		}

		/**
		 * Finds a registered customer by email and resets their password.
		 * The method sends the <em>shop:password_reset</em> notification to customer.
		 * If the customer is not found, an application exception is thrown.
		 * @documentable
		 * @param string $email Specifies the customer email address.
		 */
		public static function reset_password($email)
		{
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('shop_customers.email=?', $email)->find(null, array(), 'front_end');
			if (!$customer)
				throw new Phpr_ApplicationException('Customer with specified email is not found.');

			if ($customer->deleted_at)
				throw new Phpr_ApplicationException('Your customer account was deleted.');

			$customer->generate_password();
			$customer->save();

			$template = System_EmailTemplate::create()->find_by_code('shop:password_reset');
			if ($template)
			{
				$template->subject = $customer->set_customer_email_vars($template->subject);
				$message = $customer->set_customer_email_vars($template->content);
				$template->send_to_customer($customer, $message);
			}
		}

		/**
		 * Finds a registered customer by email and sends a password restore email.
		 * The method sends the <em>shop:password_restore</em> notification to customer.
		 * If the customer is not found, an application exception is thrown.
		 * @documentable
		 * @param string $email Specifies the customer email address.
		 */
		public static function send_password_restore($email)
		{
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('shop_customers.email=?', $email)->find(null, array(), 'front_end');
			if (!$customer)
				throw new Phpr_ApplicationException('No customer found for the specified email.');

			if ($customer->deleted_at)
				throw new Phpr_ApplicationException('Your customer account was deleted.');

			$customer->generate_password_restore();
			$customer->save();

			$template = System_EmailTemplate::create()->find_by_code('shop:password_restore');
			if ($template)
			{
				$template->subject = $customer->set_customer_email_vars($template->subject);
				$message = $customer->set_customer_email_vars($template->content);
				$template->send_to_customer($customer, $message);
				return true;
			}
			else return false;
		}

		/**
		 * Returns quantity of the item previously purchased by the customer.
		 * The function calculates only paid orders.
		 * @param Shop_Product $product A product object to return a quantity for
		 * @return int
		 */
		public function get_purchased_item_quantity($product)
		{
			if (array_key_exists($product->id, $this->product_quantity_cache))
				return $this->product_quantity_cache[$product->id];
			
			return $this->product_quantity_cache[$product->id] = Db_DbHelper::scalar('select sum(quantity) from shop_order_items, shop_orders, shop_order_status_log_records, shop_order_statuses 
			where 
			shop_order_items.shop_order_id=shop_orders.id
			and shop_order_statuses.id=shop_order_status_log_records.status_id 
			and shop_order_status_log_records.order_id=shop_orders.id
			and shop_order_statuses.code=:paid_status
			and shop_order_items.shop_product_id=:product_id
			and shop_orders.customer_id=:customer_id', array(
				'paid_status'=>Shop_OrderStatus::status_paid,
				'product_id'=>$product->id,
				'customer_id'=>$this->id
			));
		}

		/**
		 * Sets values for common customer email template variables
		 * @param string $message_text Specifies a message text to substitute variables in
		 * @return string
		 */
		public function set_customer_email_vars($message_text)
		{
			$email_scope_vars = array('customer'=>$this);
			$message_text = System_CompoundEmailVar::apply_scope_variables($message_text, 'shop:customer', $email_scope_vars);

			$message_text = str_replace('{customer_name}', h($this->name), $message_text);
			$message_text = str_replace('{customer_first_name}', h($this->first_name), $message_text);
			$message_text = str_replace('{customer_last_name}', h($this->last_name), $message_text);
			$message_text = str_replace('{customer_email}', $this->email, $message_text);
			$message_text = str_replace('{customer_password}', h($this->plain_password), $message_text);
			$message_text = str_replace('{customer_password_restore_hash}', h($this->password_restore_hash), $message_text);
			$password_restore_page = Cms_Page::create()->find_by_action_reference('shop:password_restore_request');
			if($password_restore_page)
			{
				$protocol = null;
				if ($password_restore_page->protocol != 'any')
					$protocol = $password_restore_page->protocol;
				
				$page_url = root_url($password_restore_page->url.'/'.$this->password_restore_hash, true, $protocol);
				$message_text = str_replace('{password_restore_page_link}', '<a href="'.$page_url.'">'.$page_url.'</a>', $message_text);
				$message_text = str_replace('{password_restore_page_url}', $page_url, $message_text);
			}
			
			return $message_text;
		}
		
		public function merge_into($destination)
		{
			if (!$destination || !$destination->id)
				throw new Phpr_ApplicationException('Invalid destination customer.');

			Backend::$events->fireEvent('shop:onBeforeCustomerMerged', $this, $destination);
			
			Db_DbHelper::query('update shop_orders set customer_id=:destination_customer_id where customer_id=:source_customer_id', array(
				'destination_customer_id'=>$destination->id,
				'source_customer_id'=>$this->id
			));

			Db_DbHelper::query('update shop_customer_payment_profiles set customer_id=:destination_customer_id where customer_id=:source_customer_id', array(
				'destination_customer_id'=>$destination->id,
				'source_customer_id'=>$this->id
			));
			
			Db_DbHelper::query('update shop_customer_cart_items set customer_id=:destination_customer_id where customer_id=:source_customer_id', array(
				'destination_customer_id'=>$destination->id,
				'source_customer_id'=>$this->id
			));
			
			$this->delete();
			
			Backend::$events->fireEvent('shop:onAfterCustomerMerged', $this, $destination);
		}
		
		public function send_registration_confirmation()
		{
			$template = System_EmailTemplate::create()->find_by_code('shop:registration_confirmation');
			if ($template)
			{
				$template->subject = $this->set_customer_email_vars($template->subject);
				$message = $this->set_customer_email_vars($template->content);
				$template->send_to_customer($this, $message);
			}
		}
		
		public function convert_to_registered($send_notification, $group_id)
		{
			if (Shop_Customer::find_registered_by_email($this->email))
				throw new Phpr_ApplicationException("Registered customer with email {$obj->email} already exists.");

			if ($send_notification)
				$this->generate_password();
			else
				$this->password = null;

			$this->customer_group_id = $group_id;
			$this->guest = 0;
			$this->save();
			
			if ($send_notification)
				$this->send_registration_confirmation();
		}
		
		public function before_create($deferred_session_key = null)
		{
			Backend::$events->fireEvent('shop:onCustomerBeforeCreate', $this);
		}
		
		public function after_delete()
		{
			Backend::$events->fireEvent('shop:onCustomerAfterDelete', $this);
		}

		public function set_api_fields($fields)
		{
			if (!is_array($fields))
				return;

			foreach ($fields as $field=>$value)
			{
				if (in_array($field, $this->api_added_columns))
					$this->$field = $value;
			}
		}

		public function get_display_name()
		{
			return $this->first_name.' '.$this->last_name;
		}
		
		public function eval_full_name()
		{
			return $this->first_name.' '.$this->last_name;
		}
		
		public function after_update() 
		{
			Backend::$events->fireEvent('shop:onCustomerUpdated', $this);
		}

		public function after_create() 
		{
			Backend::$events->fireEvent('shop:onCustomerCreated', $this);
		}

		public function after_save()
		{
			Backend::$events->fireEvent('shop:onCustomerSaved', $this);
		}

		public function displayField($dbName, $media = 'form')
		{
			$results = Backend::$events->fireEvent('shop:onCustomerDisplayField', $this, $dbName, $media);
			foreach ($results as $result) {
				if ($result){
					return is_string($result) ? $result : null;
				}
			}
			return parent::displayField($dbName,$media);
		}
		
		/*
		 * Customer CSV import functions
		 */
		
		public function get_csv_import_columns()
		{
			$columns = $this->get_column_definitions();

			$columns['billing_country']->listTitle = 'Billing Country';
			$columns['billing_state']->listTitle = 'Billing State';
			$columns['billing_street_addr']->listTitle = 'Billing Street Address';
			$columns['billing_city']->listTitle = 'Billing City';
			$columns['billing_zip']->listTitle = 'Billing Zip/Postal Code';

			$columns['shipping_country']->listTitle = 'Shipping Country';
			$columns['shipping_state']->listTitle = 'Shipping State';
			$columns['shipping_street_addr']->listTitle = 'Shipping Street Address';
			$columns['shipping_city']->listTitle = 'Shipping City';
			$columns['shipping_zip']->listTitle = 'Shipping Zip/Postal Code';

			$columns['shipping_first_name']->listTitle = 'Shipping First Name';
			$columns['shipping_last_name']->listTitle = 'Shipping Last Name';
			$columns['shipping_company']->listTitle = 'Shipping Company';
			$columns['shipping_phone']->listTitle = 'Shipping Phone';
			$columns['shipping_addr_is_business']->listTitle = 'Shipping address is business';

			$this->validation->add('billing_country')->required();
			
			$non_required = array('billing_country', 'billing_state', 'shipping_state', 'shipping_street_addr', 'shipping_city', 'shipping_zip', 'shipping_first_name', 'shipping_last_name');
			foreach ($non_required as $field)
			{
				$rules = $this->validation->getRule($field);
				if ($rules) 
					$rules->required = false;
			}

			unset($columns['guest']);
			unset($columns['password_confirm']);
			unset($columns['created_at']);
			unset($columns['created_user_name']);
			unset($columns['updated_at']);
			unset($columns['updated_user_name']);

			return $columns;
		}

		/*
		 * Security functions
		 */
		
		public function findUser($email, $password)
		{
			$event_result = Backend::$events->fireEvent('shop:onAuthenticateCustomer', $email, $password);
			foreach ($event_result as $event_customer)
			{
				if ($event_customer || $event_customer === false)
					return $event_customer;
			}
			
			$email = mb_strtolower($email);
			return $this->where('email=?', $email)->where('shop_customers.password=?', Phpr_SecurityFramework::create()->salted_hash($password))->where('(shop_customers.guest is null or shop_customers.guest=0)')->where('shop_customers.deleted_at is null')->find();
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the customer model.
		 * The event handler should accept two parameters - the customer object and the form 
		 * execution context string. To add new columns to the customer model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the customer object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_customers</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCustomerModel', $this, 'extend_customer_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerForm', $this, 'extend_customer_form');
		 * }
		 * 
		 * public function extend_customer_model($customer, $context)
		 * {
		 *    $customer->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_customer_form($customer, $context)
		 * {
		 *    $customer->add_form_field('x_extra_description')->tab('Customer');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomerModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerForm
		 * @see shop:onGetCustomerFieldOptions
		 * @see shop:onGetCustomerFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Customer $customer Specifies the customer object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCustomerModel($customer, $context) {}

		/**
		 * Allows to add new fields to the Create/Edit Customer form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendCustomerModel} event. 
		 * To add new fields to the customer form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * customer object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *    Backend::$events->addEvent('shop:onExtendCustomerModel', $this, 'extend_customer_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerForm', $this, 'extend_customer_form');
		 * }
		 * 
		 * public function extend_customer_model($customer, $context)
		 * {
		 *    $customer->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_customer_form($customer, $context)
		 * {
		 *    $customer->add_form_field('x_extra_description')->tab('Customer');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomerForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerModel
		 * @see shop:onGetCustomerFieldOptions
		 * @see shop:onGetCustomerFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_Customer $customer Specifies the customer object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendCustomerForm($customer, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendCustomerForm} event.
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
		 *    Backend::$events->addEvent('shop:onExtendCustomerModel', $this, 'extend_customer_model');
		 *    Backend::$events->addEvent('shop:onExtendCustomerForm', $this, 'extend_customer_form');
		 *    Backend::$events->addEvent('shop:onGetCustomerFieldOptions', $this, 'get_customer_field_options');
		 * }
		 * 
		 * public function extend_customer_model($customer, $context)
		 * {
		 *    $customer->define_column('x_drop_down', 'Some drop-down menu');
		 * }
		 * 
		 * public function extend_customer_form($customer, $context)
		 * {
		 *    $customer->add_form_field('x_drop_down')->tab('Customer')->renderAs(frm_dropdown);
		 * }
		 * 
		 * public function get_customer_field_options($field_name, $current_key_value)
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
		 * @event shop:onGetCustomerFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerModel
		 * @see shop:onExtendCustomerForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetCustomerFieldOptions($db_name, $field_value) {}
			
		/**
		 * Triggered after a customer is deleted. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onCustomerAfterDelete', $this, 'customer_deleted');
		 * }
		 *  
		 * public function customer_deleted($customer)
		 * {
		 *   if ($customer->id == 100)
		 *     // Do something
		 * }
		 * </pre>
		 * @event shop:onCustomerAfterDelete
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Customer $customer Specifies the customer object.
		 */
		private function event_onCustomerAfterDelete($customer) {}

		/**
		 * Triggered after a customer is updated. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onCustomerUpdated', $this, 'customer_updated');
		 * }
		 *  
		 * public function customer_updated($customer)
		 * {
		 *   if ($customer->fetched['first_name'] != $customer->first_name)
		 *     // Do something
		 * }
		 * </pre>
		 * @event shop:onCustomerUpdated
		 * @package shop.events
		 * @see shop:onCustomerCreated
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Customer $customer Specifies the customer object.
		 */
		private function event_onCustomerUpdated($customer) {}
		
		/**
		 * Triggered after a new customer is created. 
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onCustomerCreated', $this, 'process_new_customer');
		 * }
		 *  
		 * public function process_new_customer($customer)
		 * {
		 *   if ($customer->first_name == 'John')
		 *   {
		 *     // Do something
		 *   }
		 * }
		 * </pre>
		 * @event shop:onCustomerCreated
		 * @package shop.events
		 * @see shop:onCustomerUpdated
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Customer $customer Specifies the customer object.
		 */
		private function event_onCustomerCreated($customer) {}
			
		/**
		 * Allows to implement custom customer authentication scenarios. 
		 * By default when a customer submits the Login form, LemonStand checks the specified email 
		 * and password values against the content of the <em>shop_customers</em> database table. In 
		 * the event handler you can use submitted data for custom record search.
		 * The handler method should return the customer object ({@link Shop_Customer}), NULL or FALSE. 
		 * The FALSE value is considered as a failed authentication. If the NULL value is returned, the 
		 * standard authentication code runs.
		 * Usage example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAuthenticateCustomer', $this, 'find_customer');
		 * }
		 *  
		 * public function find_customer($login, $password)
		 * {
		 *   return Shop_Customer::create()
		 *     ->where('x_customer_profile_id=?', $login)
		 *     ->where('password=?', Phpr_SecurityFramework::create()->salted_hash($password))
		 *     ->find();
		 * }
		 * </pre>
		 * @event shop:onAuthenticateCustomer
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param string $email Specifies the customer email address.
		 * @param string $string Specifies the customer password.
		 * @return mixed Returns the customer object, NULL or FALSE.
		 */
		private function event_onAuthenticateCustomer($email, $password) {}

		/**
		 * Allows to add new buttons to the toolbar on the Customer Preview page in the Administration Area.
		 * The event handler accepts two parameters - the controller object, which you can use for rendering 
		 * a partial containing new buttons, and a {@link Shop_Customer customer} object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendCustomerPreviewToolbar', $this, 'extend_customer_toolbar');
		 * }
		 *  
		 * public function extend_customer_toolbar($controller, $customer)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/subscriptions/partials/_customer_toolbar.htm',
		 *     array(
		 *       'customer'=>$customer
		 *     ));
		 * }
		 * 
		 * // Example of the _customer_toolbar.htm partial
		 * 
		 * <? if (Subscriptions_Engine::get()->customer_has_subscriptions($customer->id)): ?>
		 *   <div class="separator">&nbsp;</div>
		 *   <?= backend_ctr_button('Subscription chart', 'subscription_chart',
		 *         url('subscriptions/chart/customer/'.$customer->id)) ?>
		 * <? endif ?>
		 * </pre>
		 * @event shop:onExtendCustomerPreviewToolbar
		 * @triggered /modules/shop/controllers/shop_customers/preview.htm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller Specifies the controller object.
		 * Use this object to render custom partials.
		 * @param Shop_Customer $customer Specifies the customer object.
		 */
		private function event_onExtendCustomerPreviewToolbar($controller, $customer) {}

		/**
		 * Allows to add new buttons to the toolbar above the customer list in the Administration Area.
		 * The event handler accepts a single parameter - the controller object, which you can use for 
		 * rendering a partial containing new buttons. Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendCustomersToolbar', $this, 'extend_customers_toolbar');
		 * }
		 *  
		 * public function extend_orders_toolbar($controller)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_customers_toolbar.htm');
		 * }
		 * 
		 * // Example of the _orders_toolbar.htm partial
		 * 
		 * <div class="separator">&nbsp;</div>
		 * <?= backend_ctr_button('My button', 'my_button_css_class', url('mymodule/manage/')) ?>
		 * </pre>
		 * @event shop:onExtendCustomersToolbar
		 * @triggered /modules/shop/controllers/shop_customers/_customers_control_panel.htm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller Specifies the controller object.
		 * Use this object to render custom partials.
		 */
		private function event_onExtendCustomersToolbar($controller) {}
		
		/**
		 * Allows to load extra CSS or JavaScript files on the Customer List, Customer Preview and Create/Edit Customer pages. 
		 * The event handler should accept a single parameter - the controller object reference. 
		 * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and 
		 * CSS files. Use paths relative to LemonStand installation URL for your resource files.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onDisplayCustomersPage', $this, 'load_resources');
		 * }
		 *  
		 * public function load_resources($controller)
		 * {
		 *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
		 *   $controller->addCss('/modules/mymodule/resources/css/my.css');
		 * }
		 * </pre>
		 * @event shop:onDisplayCustomersPage
		 * @triggered /modules/shop/controllers/shop_customers.php
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller Specifies the controller object.
		 */
		private function event_onDisplayCustomersPage($controller) {}
			
		/**
		 * Allows to add tabs to the Preview Customer page in the Administration Area. 
		 * The event handler should accept two parameters - the controller object and the customer object. 
		 * The handler should return an associative array of tab titles and corresponding tab partials.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendCustomerPreviewTabs', $this, 'add_customer_preview_tabs');
		 * }
		 *  
		 * public function add_customer_preview_tabs($controller, $customer)
		 * {
		 *   return array('Special information'=>PATH_APP.'/mymodule/partials/special.htm');
		 * }
		 * </pre>
		 * @event shop:onExtendCustomerPreviewTabs
		 * @triggered /modules/shop/controllers/shop_customers/preview.htm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller Specifies the controller object.
		 * @param Shop_Customer $customer Specifies a customer object.
		 * @return array Returns an array of tab names and tab partial paths.
		 */
		private function event_onExtendCustomerPreviewTabs($controller, $customer) {}
			
		/**
		 * Triggered after a new customer record has been imported from a CSV file. 
		 * The event handler should accept two parameters - the array of imported fields and the customer identifier. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterCsvCustomerCreated', $this, 'csv_customer_created');
		 * }
		 *  
		 * public function csv_customer_created($fields, $id)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onAfterCsvCustomerCreated
		 * @triggered /modules/shop/models/shop_customercsvimportmodel.php
		 * @see shop:onBeforeCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerUpdated
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onBeforeCsvCustomerUpdated
		 * @see shop:onAfterCsvProductUpdated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported customer fields.
		 * @param int $id Specifies the new customer identifier.
		 */
		private function event_onAfterCsvCustomerCreated($fields, $id) {}
			
		/**
		 * Triggered before a new customer record is imported from a CSV file. 
		 * The event handler should accept a single parameter - the array of imported fields. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onBeforeCsvCustomerCreated', $this, 'csv_before_customer_created');
		 * }
		 *  
		 * public function csv_before_customer_created($fields)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onBeforeCsvCustomerCreated
		 * @triggered /modules/shop/models/shop_customercsvimportmodel.php
		 * @see shop:onAfterCsvCustomerUpdated
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onBeforeCsvCustomerUpdated
		 * @see shop:onAfterCsvProductUpdated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported customer fields.
		 * @param int $id Specifies the new customer identifier.
		 */
		private function event_onBeforeCsvCustomerCreated($fields, $id) {}
			
		/**
		 * Triggered after an existing customer record has been updated from a CSV file. 
		 * The event handler should accept two parameters - the array of imported fields and the customer identifier. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onAfterCsvCustomerUpdated', $this, 'csv_customer_updated');
		 * }
		 *  
		 * public function csv_customer_updated($fields, $id)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onAfterCsvCustomerUpdated
		 * @triggered /modules/shop/models/shop_customercsvimportmodel.php
		 * @see shop:onBeforeCsvCustomerUpdated
		 * @see shop:onBeforeCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onAfterCsvProductUpdated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported customer fields.
		 * @param int $id Specifies the customer identifier.
		 */
		private function event_onAfterCsvCustomerUpdated($fields, $id) {}
			
		/**
		 * Triggered before an existing customer record is updated from a CSV file. 
		 * The event handler should accept two parameters - the array of imported fields and the customer identifier. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onBeforeCsvCustomerUpdated', $this, 'csv_before_customer_updated');
		 * }
		 *  
		 * public function csv_before_customer_updated($fields, $id)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onBeforeCsvCustomerUpdated
		 * @triggered /modules/shop/models/shop_customercsvimportmodel.php
		 * @see shop:onBeforeCsvCustomerCreated
		 * @see shop:onAfterCsvCustomerCreated
		 * @see shop:onAfterCsvProductCreated
		 * @see shop:onAfterCsvProductUpdated
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $fields Specifies a list if imported customer fields.
		 * @param int $id Specifies the customer identifier.
		 */
		private function event_onBeforeCsvCustomerUpdated($fields, $id) {}

		/**
		 * Allows to configure the Administration Area customer pages before they are displayed.
		 * In the event handler you can update the back-end controller properties. Use it for example
		 * to add custom filters for the customers list.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onConfigureCustomersPage', $this, 'configure_customers_page');
		 * }
		 *
		 * public function configure_customers_page($controller)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onConfigureCustomersPage
		 * @triggered /modules/shop/controllers/shop_customers.php
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Customers $controller Specifies the controller object.
		 */
		private function event_onConfigureCustomersPage($controller) {}

		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendCustomerForm} event.
		 * <pre>
		 * public function subscribeEvents() {
		 *   Backend::$events->addEvent('shop:onExtendCustomerModel', $this, 'extend_customer_model');
		 *   Backend::$events->addEvent('shop:onExtendCustomerForm', $this, 'extend_customer_form');
		 *   Backend::$events->addEvent('shop:onGetCustomerFieldState', $this, 'get_customer_field_state');
		 * }
		 *
		 * public function extend_customer_model($customer, $context) {
		 *   $customer->add_relation('has_and_belongs_to_many', 'test_manufacturers',
		 *     array('class_name'=>'Shop_Manufacturer', 'join_table'=>'test_customers_manufacturers',
		 *       'primary_key'=>'customer_id', 'foreign_key'=>'manufacturer_id'
		 *   ));
		 *   $customer->define_multi_relation_column('test_manufacturers', 'test_manufacturers', 'Multiple manufacturers ', '@name');
		 * }
		 *
		 * public function extend_customer_form($customer, $context) {
		 *   $customer->add_form_field('test_manufacturers')->tab('Customer')->renderAs(frm_checkboxlist);
		 * }
		 *
		 * public function get_customer_field_state($field, $value, $customer) {
		 *   if ($field == 'test_manufacturers') {
		 *     foreach ($customer->test_manufacturers as $record) {
		 *       if ($record instanceof Db_ActiveRecord) {
		 *         if ($record->id == $value)
		 *           return true;
		 *       }
		 *       elseif ($record == $value)
		 *         return true;
		 *     }
		 *    return false;
		 *  }
		 * }
		 * </pre>
		 * @event shop:onGetCustomerFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendCustomerModel
		 * @see shop:onExtendCustomerForm
		 * @see shop:onGetCustomerFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_Customer $customer Specifies the customer object.
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetCustomerFieldState($db_name, $field_value, $customer) {}

		/**
		 * Triggered after a customer record is merged.
		 * The event is triggered after the source customer's orders are copied to the destination customer 
		 * and the source customer record is deleted.
		 * @event shop:onAfterCustomerMerged
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onBeforeCustomerMerged
		 * @param Shop_Customer $source Specifies the source customer object.
		 * @param Shop_Customer $destination Specifies the destination customer object.
		 */
		private function event_onAfterCustomerMerged($source, $destination) {}

		/**
		 * Triggered before a customer record is merged.
		 * @event shop:onBeforeCustomerMerged
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onAfterCustomerMerged
		 * @param Shop_Customer $source Specifies the source customer object.
		 * @param Shop_Customer $destination Specifies the destination customer object.
		 */
		private function event_onBeforeCustomerMerged($source, $destination) {}

		/**
		 * Allows to override the default customer password generation feature.
		 * The event handler should return a new password string or null if the 
		 * default generated password should be used.
		 * @event shop:onBeforeGenerateCustomerPassword
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @return mixed The handler should return a new generated password or null.
		 */
		private function event_onBeforeGenerateCustomerPassword() {}

		/**
		 * Allows to override how a field displays in the backend user area
		 * The event handler can return an alternative string to be displayed
		 * Or to display HTML output, render the output and return true
		 * @event shop:onCustomerDisplayField
		 * @package shop.events
		 * @author Matt Manning
		 * @return mixed The handler should return a string or boolean.
		 */
		private function event_onCustomerDisplayField() {}

	}

?>