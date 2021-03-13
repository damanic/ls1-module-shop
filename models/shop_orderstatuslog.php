<?php

	/**
	 * Manages order status transitions. 
	 * This class can be used for changing an order current status.
	 * @documentable
	 * @see Shop_Order
	 * @see Shop_OrderStatus
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_OrderStatusLog extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_status_log_records';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = false;
		
		public $status_ids = array();

		public $calculated_columns = array(
			'status_name'=>array(
				'sql'=>'shop_order_statuses.name', 
				'join'=>array('shop_order_statuses'=>'shop_order_statuses.id=status_id'), 'type'=>db_text),
			'status_color'=>array('sql'=>'shop_order_statuses.color')
		);

		public $belongs_to = array(
			'status'=>array('class_name'=>'Shop_OrderStatus', 'foreign_key'=>'status_id')
		);
		
		public $custom_columns = array('send_notifications'=>db_bool);
		
		public $role_id = null;
		public $send_notifications = true;
		
		public $api_added_columns = array();

		public static function create()
		{
			return new self();
		}
		
		/**
		 * Changes an order's status.
		 * The method doesn't check whether the transition from the current order status to the new status is allowed.
		 * Customer and user notifications are sent if <em>$send_notifications</em> parameter value is TRUE and if the
		 * notifications are allowed by the {@link http://lemonstand.com/docs/configuring_the_order_route_and_user_roles order route}.
		 * 
		 * The behavior of this method can be altered by {@link shop:onOrderBeforeStatusChanged} event handlers.
		 * @documentable
		 * @see http://lemonstand.com/docs/configuring_the_order_route_and_user_roles Configuring the order route and user roles
		 * @see shop:onOrderBeforeStatusChanged
		 * @param integer $status_id Specifies a new order status identifier.
		 * @param Shop_Order $order An order object to change status for.
		 * @param string $comment Specifies an optional status transition comment.
		 * @param boolean $send_notifications Determines whether notifications should be sent to the customer and LemonStand users.
		 * @param array $api_data A list of API field values
		 * @return boolean Returns TRUE if the status has been successfully changed. Returns FALSE otherwise.
		 */
		public static function create_record($status_id, $order, $comment = null, $send_notifications = true, $api_data = array())
		{
			if ($status_id == $order->status_id)
				return false;

			$prev_status = $order->status_id;
			$return = Backend::$events->fireEvent('shop:onOrderBeforeStatusChanged', $order, $status_id, $prev_status, $comment, $send_notifications);
			foreach ($return as $result)
			{
				if($result === false)
					return false;
			}


			if(!Shop_OrderStatus::order_meets_status_requirements($status_id, $order)){
				throw new Phpr_ApplicationException('The order does not meet the requirements for this status transition.');
			}


			$log_record = self::create();
			$log_record->init_columns_info();
			$log_record->status_id = $status_id;
			$log_record->order_id = $order->id;
			$log_record->comment = $comment;
			
			foreach ($log_record->api_added_columns as $api_column_id)
			{
				if (array_key_exists($api_column_id, $api_data))
					$log_record->$api_column_id = $api_data[$api_column_id];
			}
			
			$log_record->save();

			Db_DbHelper::query('update shop_orders set status_id=:status_id, status_update_datetime=:datetime where id=:id', array(
				'status_id'=>$status_id,
				'datetime'=>Phpr_Date::userDate(Phpr_DateTime::now()),
				'id'=>$order->id
			));
			
			$paid_status = Shop_OrderStatus::get_status_paid();
			
			if ($status_id == $paid_status->id)
			{
				Db_DbHelper::query('update shop_orders set payment_processed=:payment_processed where id=:id', array(
					'payment_processed'=>Phpr_DateTime::now(),
					'id'=>$order->id
				));

				Core_Metrics::log_order($order);
			}
			
			$status = Shop_OrderStatus::create()->find($status_id);
			if ($status && $status->update_stock && !$order->stock_updated)
			{
				$change_processed = Backend::$events->fireEvent('shop:onOrderStockChange', $order, $paid_status);
				$stock_change_cancelled = false;
				foreach ($change_processed as $value) 
				{
					if ($value)
					{
						$stock_change_cancelled = true;
						break;
					}
				}

				if (!$stock_change_cancelled)
					$order->update_stock_values();
			}
			
			/*
			 * Send email message to the status recipients and customer
			 */
			if ($status)
			{
				Backend::$events->fireEvent('shop:onOrderStatusChanged', $order, $status, $prev_status);

				if ($send_notifications)
				{
					$status->send_notifications($order, $comment);
				}
			}

			/*
			 * Handle Locking
			 */
			if ($status && $status->order_lock_action) {
				if($order->is_order_locked() && $status->unlocks_order()){
					$order->unlock_order();
					$order->save();
					$order->status_id = $status->id;
					Shop_OrderLockLog::add_log($order,'Unlocked by status change');
				} else if(!$order->is_order_locked() && $status->locks_order()){
					$order->lock_order();
					$order->save();
					$order->status_id = $status->id;
					Shop_OrderLockLog::add_log($order,'Locked by status change');
				}

			}
				
			return true;
		}
		
		public function get_status_options()
		{
			$statuses = Shop_OrderStatus::create();
			
			if (!count($this->status_ids))
			{
				$statuses->join('shop_status_transitions', 'shop_status_transitions.to_state_id=shop_order_statuses.id'); 
				$statuses->where('shop_status_transitions.from_state_id=?', $this->status_id);
				$statuses->where('shop_status_transitions.role_id=?', $this->role_id);
			} else
			{
				$end_transitions = Shop_StatusTransition::listAvailableTransitionsMulti($this->role_id, $this->status_ids);
				$end_status_ids = array();
				foreach ($end_transitions as $transition)
					$end_status_ids[$transition->to_state_id] = 1;
					
				$end_status_ids = array_keys($end_status_ids);

				$statuses->where('shop_order_statuses.id in (?)', array($end_status_ids));
			}

			return $statuses->order('name')->find_all()->as_array('name', 'id');
		}

		public function define_columns($context = null)
		{
			$this->define_relation_column('status', 'status', 'Status ', db_varchar, '@name')->validation()->required('Please select new order status');
			$this->define_column('comment', 'Comment')->validation()->fn('trim');
			$this->define_column('send_notifications', 'Send email notifications');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendOrderStatusLogModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$field = $this->add_form_field('status')->referenceSort('name')->emptyOption('<please select status>');
			if ($context != 'multiorder')
				$field->tab('Status');

			$field = $this->add_form_field('comment')->comment('If configured, the comment may appear in customer email notifications.', 'above', true)->commentTooltip('Use the {order_status_comment} variable in email templates<br/>if you want to add order status comments to customer notifications.');
			if ($context != 'multiorder')
				$field->tab('Status');

			$field = $this->add_form_field('send_notifications')->comment('Send notifications to customer(s) and LemonStand users in accordance with the order route settings.', 'above');
			if ($context != 'multiorder')
				$field->tab('Status');
				
			Backend::$events->fireEvent('shop:onExtendOrderStatusLogForm', $this, $context);
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
			$result = Backend::$events->fireEvent('shop:onGetOrderStatusLogFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetOrderStatusLogFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public static function get_latest_transition_to($order_id, $status_id)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order_id);
			$obj->where('status_id=?', $status_id);
			$obj->order('id desc');
			return $obj->find();
		}

		public static function order_has_status_code($order, $status_codes){

			$status_ids = array();

			if(!is_array($status_codes)) {
				$status_codes = array( $status_codes );
			}

			foreach($status_codes as $code){
				$status = Shop_OrderStatus::get_by_code($code);
				if($status){
					$status_ids[] = $status->id;
				}
			}

			if(!count($status_ids))
				return false;

			$order_id = Db_DbHelper::scalar("
			 SELECT order_id
			 FROM shop_order_status_log_records
			 WHERE status_id IN (?)
			 AND order_id = '".$order->id."' LIMIT 1", array($status_ids));

			if($order_id > 0){
				return true;
			}

			return false;

		}


		public function set_default_email_notify_checkbox() 
		{
			$this->send_notifications = Db_UserParameters::get('orders_email_on_status_change', null, '1');
		}

		/*
		 * Event descriptions
		 */

		/**
		 * Triggered before an order changes its status.
		 * The event allows to cancel the status update. To cancel the update the handler should return FALSE. Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onOrderBeforeStatusChanged', $this, 'before_status_changed');
		 * }
		 *  
		 * public function before_status_changed($order, $new_status_id, $prev_status_id, $comments, $send_notifications)
		 * {
		 *   $status = Shop_OrderStatus::create()->find($new_status_id);
		 * 
		 *   //for orders over $250, set the status to "New big order" instead of "New"
		 *   if($status && $status->code == 'new' && $order->subtotal >= 250)
		 *   {
		 *     $new_status = Shop_OrderStatus::create()->find_by_code('new_big');
		 *     if($new_status)
		 *     {
		 *       Shop_OrderStatusLog::create_record($new_status->id, $order, $comments, $send_notifications);
		 *       return false;
		 *     }
		 *   }
		 * }
		 * </pre>
		 * @event shop:onOrderBeforeStatusChanged
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onOrderStatusChanged
		 * @see Shop_OrderStatusLog::create_record()
		 * @param Shop_Order $order Specifies the order object.
		 * @param integer $status_id Specifies a new order status identifier.
		 * @param integer $prev_status_id Specifies a previous order status identifier.
		 * @param string $comment Specifies an optional status transition comment.
		 * @param boolean $send_notifications Determines whether notifications should be sent to the customer and LemonStand users.
		 * @return boolean Returns FALSE if the status transition should be cancelled.
		 */
		private function event_onOrderBeforeStatusChanged($order, $status_id, $prev_status_id, $comment, $send_notifications) {}
			
		/**
		 * Triggered after an order has changed its status. 
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onOrderStatusChanged', $this, 'process_status_change');
		 * }
		 *  
		 * public function process_status_change($order, $new_status, $prev_status_id)
		 * {
		 *   if ($new_status->code == 'paid')
		 *     // Do something
		 * }
		 * </pre>
		 * @event shop:onOrderStatusChanged
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see shop:onOrderBeforeStatusChanged
		 * @see Shop_OrderStatusLog::create_record()
		 * @param Shop_Order $order Specifies the order object.
		 * @param Shop_OrderStatus $status Specifies a new order status object.
		 * @param integer $prev_status_id Specifies a previous order status identifier.
		 */
		private function event_onOrderStatusChanged($order, $status, $prev_status_id) {}
			
		/**
		 * Triggered before LemonStand updates inventory for products of a specific order. 
		 * You can cancel the inventory update process by returning TRUE from the the event handler. 
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onOrderStockChange', $this, 'on_stock_change');
		 * }
		 *  
		 * public function on_stock_change($order, $status)
		 * {
		 *   // Do something
		 *   
		 *   ...
		 *   
		 *   // Return TRUE to suppress the default LemonStand inventory update
		 *   return true;
		 * }
		 * </pre>
		 * @event shop:onOrderStockChange
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @see Shop_OrderStatusLog::create_record()
		 * @param Shop_Order $order Specifies the order object.
		 * @param Shop_OrderStatus $status Specifies a new order status object.
		 * @return boolean Returns TRUE if the inventory update should be cancelled.
		 */
		private function event_onOrderStockChange($order, $_status) {}
	}

?>