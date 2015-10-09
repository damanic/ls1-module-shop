<?

	/**
	 * Represents a status of an {@link Shop_Order order}. 
	 * Usually you don't need to access the order status class properties directly. The {@link Shop_Order} class has hidden 
	 * status fields which can be accessed through the {@link Db_ActiveRecord::displayField() displayField()} method of the order object. 
	 * It is preferable to use this method instead of accessing the <em>$status</em> order's property directly because of the performance considerations. 
	 * There are 2 status-related fields which you can load from an order object with {@link Db_ActiveRecord::displayField() displayField()} method: 
	 * <ul>
	 *   <li><em>status</em> – status name.</li>
	 *   <li><em>status_color</em> – status color.</li>
	 * </ul>
	 * Example:
	 * <pre>
	 * Order status:
	 * <span style="color: <?= $order->displayField('status_color') ?>">
	 *   <?= $order->displayField('status') ?>
	 * </span>
	 * </pre>
	 * Use {@link Shop_OrderStatusLog::create_record()} method for changing orders' current status.
	 * @property integer $id Specifies the status record identifier.
	 * @property string $code Specifies the status API code.
	 * @property string $name Specifies the status name.
	 * @property string $color Specifies the status color in HEX format (<em>#9acd32</em>). 
	 * This field can be used for customize the order list on the {@link http://lemonstand.com/docs/customer_orders_page Customer Orders} page.
	 * @property boolean $notify_customer Determines whether a customer should be notified when an order enters this status.
	 * @property boolean $notify_recipient Determines whether LemonStand users responsible for processing orders with this status should be notified when an order enters this status.
	 * @documentable
	 * @see http://lemonstand.com/docs/configuring_the_order_route_and_user_roles Configuring the order route and user roles
	 * @see Shop_Order
	 * @see Shop_OrderStatusLog
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
 	 */
	class Shop_OrderStatus extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_statuses';
		public $enabled = true;
		
		protected static $code_cache = array();
		
		const status_new = 'new';
		const status_paid = 'paid';

		public static $colors = array(
			'#32cd32', '#9acd32', '#808000', '#ffd700', '#ff8c00', '#daa520', 
			'#ffb6c1', '#cc6666', '#a0522d', '#ff0000', '#ffcc99', '#9370d8', 
			'#0000ff', '#708090', '#0099cc', '#99ccff', '#ff6600', '#fcd202', 
			'#f8ff01', '#b0de09', '#04d215', '#0d8ecf', '#0d52d1', '#2a0cd0', 
			'#8a0ccf', '#cd0d74', '#754deb', '#999999', '#dddddd', '#333333'
		);
		
		public $has_many = array(
			'outcoming_transitions'=>array('class_name'=>'Shop_StatusTransition', 'foreign_key'=>'from_state_id', 'delete'=>true, 'order'=>'id')
		);
		
		public $has_and_belongs_to_many = array(
			'notifications'=>array('class_name'=>'Shop_Role', 'join_table'=>'shop_status_notifications', 'order'=>'name', 'foreign_key'=>'shop_role_id', 'primary_key'=>'shop_status_id')
		);

		public $belongs_to = array(
			'customer_message_template'=>array('class_name'=>'System_EmailTemplate', 'foreign_key'=>'customer_message_template_id', 'conditions'=>'(is_system is null or is_system = 0)'),
			'system_message_template'=>array('class_name'=>'System_EmailTemplate', 'foreign_key'=>'admin_message_template_id', 'conditions'=>'(is_system is not null and is_system = 1)')
		);
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify status name.");
			$this->define_column('color', 'Color')->invisible()->validation()->required("Please select status color.");
			$this->define_multi_relation_column('transitions', 'outcoming_transitions', 'Transitions', "concat((select name from shop_order_statuses where shop_order_statuses.id=shop_status_transitions.to_state_id), ' (', (select name from shop_roles where shop_roles.id=shop_status_transitions.role_id),')')");
			$this->define_column('notify_customer', 'Notify Customer')->validation();
			$this->define_column('notify_recipient', 'Notify Transition Recipients')->validation(); 
			$this->define_column('update_stock', 'Update Stock')->validation(); 

			$this->define_relation_column('customer_message_template', 'customer_message_template', 'Customer Message Template', db_varchar, '@code')->validation()->method('validate_message_template');
			$this->define_relation_column('system_message_template', 'system_message_template', 'System Message Template', db_varchar, '@code')->validation()->method('validate_system_message_template');
			
			$this->define_column('code', 'API Code')->validation()->fn('trim')->unique('The code "%s" already in use.');
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if (!$front_end)
				$this->define_multi_relation_column('notifications', 'notifications', 'Notify User Roles', '@name')->defaultInvisible();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendOrderStatusModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->tab('Order Status');
			$this->add_form_field('update_stock')->tab('Order Status')->comment('Update stock values when an order enters this status.', 'above');
			$this->add_form_field('color')->tab('Order Status')->renderAs('state_colors')->comment('Color for indicating the status in the order list.', 'above');

			if ($this->code != self::status_new && $this->code != self::status_paid)
				$this->add_form_field('code')->tab('Order Status')->comment('You can use the API code for identifying the status in API calls.', 'above');

			$this->add_form_field('transitions')->tab('Transitions')->renderAs('status_transitions')->comment('A list of order statuses an order can be transferred from this status and user roles responsible for transitions.', 'above')->referenceSort('id');
			$this->add_form_field('notify_customer')->tab('Notifications')->comment('Notify customer when orders enter this status.');
			$this->add_form_field('customer_message_template')->tab('Notifications')->comment('Please select an email message template to send to customer. To manage email templates open <a target="_blank" href="'.url('/system/email_templates').'">Email Templates</a> page.', 'above', true)->renderAs(frm_dropdown)->emptyOption('<please select template>')->cssClassName('checkbox_align'); 
			$this->add_form_field('notify_recipient')->tab('Notifications')->comment('Notify users responsible for processing orders with this status when an order enters this status.');
			$this->add_form_field('notifications')->tab('Notifications')->comment('Alternatively you can select user roles which should receive a notification when orders enter this status.', 'above');
			$this->add_form_field('system_message_template')->tab('Notifications')->comment('Please select an email message template to send to users. To manage email templates open <a target="_blank" href="'.url('/system/email_templates').'">Email Templates</a> page. The notification is sent only if the Notify Transition Recipients option is enabled or a user role is selected in the Notify User Roles list above.', 'above', true)->renderAs(frm_dropdown)->emptyOption('<please select template>'); 
			
			Backend::$events->fireEvent('shop:onExtendOrderStatusForm', $this, $context);
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
			$result = Backend::$events->fireEvent('shop:onGetOrderStatusFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetOrderStatusFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function before_delete($id=null)
		{
			if ($this->code == self::status_new || $this->code == self::status_paid)
				throw new Phpr_ApplicationException("Statuses New and Paid cannot be deleted.");

			$bind = array('id'=>$this->id);
			$in_use = Db_DbHelper::scalar('select count(*) from shop_orders where status_id=:id', $bind);
			if ($in_use)
				throw new Phpr_ApplicationException("Cannot delete status because it is in use.");

			Shop_PaymentMethod::order_status_deletion_check($this);
		}

		public function after_delete()
		{
			$transitions = Shop_StatusTransition::create()->where('to_state_id=?', $this->id)->find_all();
			foreach ($transitions as $transition)
				$transition->delete();
				
			$transitions = Shop_StatusTransition::create()->where('from_state_id=?', $this->id)->find_all();
			foreach ($transitions as $transition)
				$transition->delete();
		}

		public static function get_status_new()
		{
			return self::create()->find_by_code(self::status_new);
		}
		
		public static function get_status_paid()
		{
			return self::create()->find_by_code(self::status_paid);
		}
		
		public function validate_message_template($name, $value)
		{
			if (!$value && $this->notify_customer)
				$this->validation->setError('Please select email message template', $name, true);
				
			return true;
		}
		
		public function validate_system_message_template($name, $value)
		{
			if (!$value && $this->notify_recipient)
				$this->validation->setError('Please select email message template', $name, true);
				
			return true;
		}
		
		public function send_notifications($order, $comment)
		{
			/*
			 * Check whether the New Order Notification is allowed
			 */
			
			$notification_allowed = true;
			
			if ($this->code == Shop_OrderStatus::status_new)
			{
				$payment_method = Shop_PaymentMethod::create()->find($order->payment_method_id);
				if ($payment_method)
				{
					$payment_method->define_form_fields();
					$notification_allowed = $payment_method->get_paymenttype_object()->allow_new_order_notification($payment_method, $order);
				}
			}
			
			/*
			 * Send notifications to the application users
			 */
			
			$users = Users_User::create()->from('users', 'distinct users.*');
			$users->join('shop_status_notifications', 'shop_status_notifications.shop_status_id=\''.$this->id.'\'');
			$users->where('shop_status_notifications.shop_role_id=users.shop_role_id');
			$users->where('(users.status is null or users.status = 0)');

			$status_users = $users->find_all();
			$users_to_notify = $status_users->as_array(null, 'email');

			if ($this->notify_recipient)
			{
				
				$users = Users_User::create()->from('users', 'distinct users.*');
				$users->join('shop_status_transitions', 'shop_status_transitions.from_state_id=\''.$this->id.'\'');
				$users->where('shop_role_id=shop_status_transitions.role_id');
				$users->where('(users.status is null or users.status = 0)');
				
				if ($status_users->count)
				{
					$user_ids = $status_users->as_array('id');
					$users->where('users.id not in (?)', array($user_ids));
				}

				$transition_users = $users->find_all();
				foreach ($transition_users as $user)
					$users_to_notify[$user->email] = $user;
			}

			if ($users_to_notify)
			{
				if ($this->code == Shop_OrderStatus::status_new)
					$order = Shop_Order::create()->find($order->id);

				$template = $this->system_message_template;

				if ($template)
				{
					$stop_message = false;
					$result = Backend::$events->fireEvent('shop:onBeforeOrderInternalStatusMessageSent', $order, $this);
					foreach ($result as $value) 
					{
						if ($value === false)
							$stop_message = true;
					}

					if (!$stop_message)
						$order->send_team_notifications($template, new Db_DataCollection($users_to_notify), $comment, array('prev_status'=>$this));
				}
			}
			
			/*
			 * Send notification to customer
			 */
			if ($this->notify_customer && $notification_allowed)
			{
				$template = $this->customer_message_template;
				if ($template)
				{
					try
					{
						$stop_message = false;
						$result = Backend::$events->fireEvent('shop:onBeforeOrderCustomerStatusMessageSent', $order, $this);
						foreach ($result as $value)
						{
							if ($value === false)
								$stop_message = true;
						}
						if (!$stop_message)
							$order->send_customer_notification($template, $comment, array('prev_status'=>$this));
					} catch (Exception $ex){}
				}
			}
		}
		
		public static function list_all_statuses()
		{
			$result = self::create();
			return $result->order('name asc')->find_all();
		}
		
		/**
		 * Returns a status object by its API code.
		 * @documentable 
		 * @param string $code Specifies the status API code.
		 * @return Shop_OrderStatus Returns the order status object. Returns NULL if the status is not found.
		 */
		public static function get_by_code($code)
		{
			if (array_key_exists($code, self::$code_cache))
				return self::$code_cache[$code];
				
			$status = Shop_OrderStatus::create()->find_by_code($code);  
			
			return self::$code_cache[$code] = $status;
		}

		/*
		 * Event descriptions
		 */

		/**
		 * Allows to define new columns in the order status model.
		 * The event handler should accept two parameters - the order status object and the form 
		 * execution context string. To add new columns to the order status model, call the {@link Db_ActiveRecord::define_column() define_column()}
		 * method of the status object. Before you add new columns to the model, you should add them to the
		 * database (the <em>shop_order_statuses</em> table).
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
		 * }
		 *  
		 * public function extend_order_status_model($status, $context)
		 * {
		 *   $status->define_column('x_custom_column', 'Custom column')->invisible();
		 * }
		 * 
		 * public function extend_order_status_form($status, $context)
		 * {
		 *   $status->add_form_field('x_custom_column')->tab('Order Status');
		 * }
		 * </pre>
		 * @event shop:onExtendOrderStatusModel
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOrderStatusForm
		 * @see shop:onGetOrderStatusFieldOptions
		 * @see shop:onGetOrderStatusFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_OrderStatus $status Specifies the order status object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendOrderStatusModel($status, $context) {}
			
		/**
		 * Allows to add new fields to the Create/Edit Order Status form in the Administration Area. 
		 * Usually this event is used together with the {@link shop:onExtendOrderStatusModel} event. 
		 * To add new fields to the status form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the 
		 * status object.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
		 * }
		 *  
		 * public function extend_order_status_model($status, $context)
		 * {
		 *   $status->define_column('x_custom_column', 'Custom column')->invisible();
		 * }
		 * 
		 * public function extend_order_status_form($status, $context)
		 * {
		 *   $status->add_form_field('x_custom_column')->tab('Order Status');
		 * }
		 * </pre>
		 * @event shop:onExtendOrderStatusForm
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOrderStatusModel
		 * @see shop:onGetOrderStatusFieldOptions
		 * @see shop:onGetOrderStatusFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param Shop_OrderStatus $status Specifies the order status object.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendOrderStatusForm($status, $context) {}
			
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link shop:onExtendOrderStatusForm} event.
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
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
		 *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
		 *   Backend::$events->addEvent('shop:onGetOrderStatusFieldOptions', $this, 'get_order_status_field_options');
		 * }
		 *  
		 * public function extend_order_status_model($status, $context)
		 * {
		 *   $status->define_column('x_custom_column', 'Custom column')->invisible();
		 * }
		 * 
		 * public function extend_order_status_form($status, $context)
		 * {
		 *   $status->add_form_field('x_custom_column')->tab('Order Status')->renderAs(frm_dropdown);
		 * }
		 *
		 * public function get_order_status_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_custom_column')
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
		 * @event shop:onGetOrderStatusFieldOptions
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOrderStatusModel
		 * @see shop:onExtendOrderStatusForm
		 * @see shop:onGetOrderStatusFieldState
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetOrderStatusFieldOptions($db_name, $field_value) {}
			
		/**
		 * Determines whether a custom radio button or checkbox list option is checked.
		 * This event should be handled if you added custom radio-button and or checkbox list fields with {@link shop:onExtendOrderStatusForm} event.
		 * @event shop:onGetOrderStatusFieldState
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onExtendOrderStatusModel
		 * @see shop:onExtendOrderStatusForm
		 * @see shop:onGetOrderStatusFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @param Shop_OrderStatus $status Specifies the order status object.
		 * @return boolean Returns TRUE if the field is checked. Returns FALSE otherwise.
		 */
		private function event_onGetOrderStatusFieldState($db_name, $field_value, $status) {}

		/**
		 * Allows to cancel the internal user notification when an order changes its status.
		 * The handler should return FALSE value if the notification should not be sent.
		 * @event shop:onBeforeOrderInternalStatusMessageSent
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Order $order Specifies the order object.
		 * @param Shop_OrderStatus $status Specifies the order status object.
		 * @return boolean Returns FALSE if the internal notification should be stopped.
		 */
		private function event_onBeforeOrderInternalStatusMessageSent($order, $status) {}

		/**
		 * Allows to cancel the customer notification when an order changes its status.
		 * The handler should return FALSE if the notification should not be sent.
		 * <pre>
		 * public function subscribeEvents() {
		 *   Backend::$events->addEvent('shop:onBeforeOrderCustomerStatusMessageSent', $this, 'before_send_customer_message');
		 * }
		 * public function before_send_customer_message($order, $status)
		 * {
		 *   //skip sending customer order status change notifications for free orders
		 *   if($order->total == 0)
		 *     return false;
		 * }
		 * </pre>
		 * @event shop:onBeforeOrderCustomerStatusMessageSent
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_Order $order Specifies the order object.
		 * @param Shop_OrderStatus $status Specifies the order status object.
		 * @return boolean Returns FALSE if the customer notification should be stopped.
		 */
		private function event_onBeforeOrderCustomerStatusMessageSent($order, $status) {}
	}

?>