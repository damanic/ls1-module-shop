<?php

	class Shop_CartPriceRule extends Shop_PriceRuleBase
	{
		public $table_name = 'shop_cart_rules';

		public $has_and_belongs_to_many = array(
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_cart_rules_customer_groups', 'order'=>'name')
		);
		
		public $has_many = array(
			'rule_conditions'=>array(
				'class_name'=>'Shop_PriceRuleCondition',
				'foreign_key'=>'rule_host_id',
				'conditions'=>"host_rule_set='cart_conditions' and rule_parent_id is null"),

			'products_conditions'=>array(
				'class_name'=>'Shop_PriceRuleCondition',
				'foreign_key'=>'rule_host_id',
				'conditions'=>"host_rule_set='products_conditions' and rule_parent_id is null"),
		);
		
		public $belongs_to = array(
			'coupon'=>array('class_name'=>'Shop_Coupon', 'foreign_key'=>'coupon_id')
		);
		
		/*
		 * Calculated fields definition. Example: 
		 * public $calculated_columns = array( 
		 * 	'comment_num'=>'select count(*) from comments where post_id=post.id',
		 * 	'disk_file_name'=>array('sql'=>'files.file_create_date', 'join'=>array('files'=>'files.post_id=post.id'), 'type'=>db_date)
		 * );
		 *
		 * @var array
		 */
		public $calculated_columns = array(
			'coupon_code'=>'select shop_coupons.code from shop_coupons where shop_coupons.id=shop_cart_rules.coupon_id',
			'customer_group_ids_str'=>"select group_concat(shop_customer_group_id separator ',') from shop_cart_rules_customer_groups where shop_cart_rules_customer_groups.shop_cart_rule_id=shop_cart_rules.id"
		);
		
		public $custom_columns = array('rule_conditions_field'=>db_text, 'products_conditions_field'=>db_text);
		protected $conditions_root = false;
		protected $products_conditions_root = false;
		protected static $active_rules = null;
		protected static $customer_usage_map = null;

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->validation()->fn('trim')->required("Please specify the price rule name.");
			$this->define_column('description', 'Description')->validation()->fn('trim')->required('Please specify the rule description');
			$this->define_column('sort_order', 'Sort Order')->order('asc')->invisible();
			$this->define_column('active', 'Active');
			$this->define_column('date_start', 'From Date')->timeFormat('%H:%M')->validation();
			$this->define_column('date_end', 'To Date')->timeFormat('%H:%M')->validation();

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			if (!$front_end)
				$this->define_relation_column('coupon', 'coupon', 'Coupon', db_varchar, "@code")->invisible();
			
			$this->define_column('max_coupon_uses', 'Max uses per coupon')->validation();
			$this->define_column('max_customer_uses', 'Max uses per customer')->validation();
			$this->define_column('terminating', 'Terminating Rule');
			
			if (!$front_end)
			{
				$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', '@name');
				$this->define_multi_relation_column('customer_group_ids', 'customer_groups', 'Customer Group Ids', '@id');
			}

			$this->define_column('rule_conditions_field', 'Conditions');
			$this->define_column('products_conditions_field', 'Products Conditions');
			$this->define_column('action_class_name', 'Action');
			$this->define_column('free_shipping', 'Free shipping');
		}
		
		public function after_validation($deferred_session_key = null)
		{
			if (strlen($this->date_start) && strlen($this->date_end))
			{
				$start_obj = Phpr_DateTime::parse($this->date_start, Phpr_DateTime::universalDateFormat);
				$end_obj = Phpr_DateTime::parse($this->date_end, Phpr_DateTime::universalDateFormat);

				if ($start_obj && $end_obj && $start_obj->compare($end_obj) > 0)
					$this->validation->setError('The start date cannot be less than the end date.', 'date_start', true);
			}
			
			return true;
		}
		
		public function define_form_fields($context = null)
		{
			if ($context != 'eval_discounts')
			{
				$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			
				$this->add_form_field('active')->tab('Rule Settings');
				$this->add_form_field('name')->tab('Rule Settings');
				$this->add_form_field('description')->tab('Rule Settings')->size('tiny');
				$this->add_form_field('date_start', 'left')->placeholder('00:00','time')->tab('Rule Settings')->comment('Optional time in 24 hour format hh:mm. Default 00:00 (midnight)');
				$this->add_form_field('date_end', 'right')->placeholder('00:00','time')->tab('Rule Settings')->comment('Optional time in 24 hour format hh:mm. Default 00:00 (midnight)');

				if (!$front_end)
					$this->add_form_field('coupon')->tab('Rule Settings');

				$this->add_form_field('max_coupon_uses', 'left')->tab('Rule Settings');
				$this->add_form_field('max_customer_uses', 'right')->tab('Rule Settings');
			
				if (!$front_end)
					$this->add_form_field('customer_groups')->tab('Rule Settings')->comment('Please select customer groups the rule is enabled for. Do not select any group to make the rule enabled for all customer groups.', 'above');
			
				$this->add_form_field('rule_conditions_field')->tab('Conditions');
				$this->add_form_field('terminating')->comment('Stop processing other rules if this rule took effect.')->tab('Action');
				$this->add_form_field('action_class_name')->tab('Action')->renderAs(frm_dropdown);
			
				$this->form_tab_css_class('Conditions', 'conditions_tab');
			
				if (!strlen($this->action_class_name))
				{
					$actions = $this->get_action_class_name_options();
					$action_classes = array_keys($actions);
					$this->action_class_name = $action_classes[0];
				}
			
				// $this->add_form_field('free_shipping')->tab('Free Shipping');
			
				$this->add_custom_field('free_shipping_options', 'Free shipping options')->tab('Free Shipping')->renderAs(frm_checkboxlist)->comment('Please select the shipping options that you want to be free, if all conditions specified on the Conditions tab are satisfied.', 'above');

				parent::define_form_fields($context);

				$this->add_form_field('products_conditions_field')->tab('Action');
			} 
			else
			{
				parent::define_form_fields($context);
				$this->add_form_field('products_conditions_field')->tab('Action');
			}
		}
		
		public function get_free_shipping_options_options($current_key_value = -1)
		{
			$result = array();
			$shipping_options = Shop_ShippingOption::create()->where('enabled = 1')->find_all();
			foreach ($shipping_options as $option)
			{
				$option->define_form_fields();
				$option_options = $option->list_enabled_options();
				
				foreach ($option_options as $option_id=>$option_item)
				{
					$full_option_name = trim($option_item->method_name);
					if ($option_item->option_name)
						$full_option_name .= ' - '.$option_item->option_name;

					$result[$option_id] = $full_option_name;
				}
			}
			
			return $result;
		}
		
		public function get_free_shipping_options_optionState($value = 1)
		{
			return is_array($this->free_shipping_options) && in_array($value, $this->free_shipping_options);
		}
		
		public function get_coupon_options($key = -1)
		{
			$options = array();
			$options[0] = '<no coupon code assigned>';
			$options[-1] = '<create new coupon>';

			$coupons = Shop_Coupon::create()->order('code')->find_all();
			foreach ($coupons as $coupon)
				$options[$coupon->id] = $coupon->code;

			return $options;
		}

		public function get_action_class_name_options($key = -1)
		{
			$result = array();
			$actions = Shop_RuleActionBase::find_actions_by_type(Shop_RuleActionBase::type_cart);
			
			foreach ($actions as $action_class)
			{
				$action_obj = new $action_class();
				$result[$action_class] = $action_obj->get_name();
			}
			
			asort($result);
			
			return $result;
		}

		public function after_create() 
		{
			Db_DbHelper::query('update shop_cart_rules set sort_order=:sort_order where id=:id', array(
				'sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->sort_order = $this->id;
		}

		public function before_delete($id=null){
			Backend::$events->fireEvent('shop:onCartPriceRuleBeforeDelete', $this, $id);
		}

		public function init_conditions($session_key)
		{
			$root_rule = new Shop_PriceRuleCondition();
			$root_rule->host_rule_set = 'cart_conditions';
			$root_rule->class_name = 'Shop_RuleIfCondition';
			$root_rule->define_form_fields();
			$root_rule->save();

			$this->rule_conditions->add($root_rule, $session_key);

			$root_rule = new Shop_PriceRuleCondition();
			$root_rule->host_rule_set = 'products_conditions';
			$root_rule->class_name = 'Shop_RuleIfCondition';
			$root_rule->define_form_fields();
			$root_rule->save();

			$this->products_conditions->add($root_rule, $session_key);
		}
		
		public function get_conditions_root($form_session_key = null, $allow_caching = false)
		{
			if ($this->conditions_root !== false && $allow_caching)
				return $this->conditions_root;
			
			if ($form_session_key)
				$root_conditions = $this->list_related_records_deferred('rule_conditions', $form_session_key);
			else
				$root_conditions = $this->rule_conditions;
				
			if ($root_conditions->count)
				return $this->conditions_root = $root_conditions[0];
				
			return $this->conditions_root = null;
		}

		public function get_products_conditions_root($form_session_key = null, $allow_caching = false)
		{
			if ($this->products_conditions_root !== false && $allow_caching)
				return $this->products_conditions_root;

			if ($form_session_key)
				$root_conditions = $this->list_related_records_deferred('products_conditions', $form_session_key);
			else
				$root_conditions = $this->products_conditions;
			
			if ($root_conditions->count)
				return $this->products_conditions_root = $root_conditions[0];
				
			return $this->products_conditions_root = null;
		}

		public function is_actual_for($payment_method, $shipping_method, $cart_items, $shipping_address, $subtotal, $cart_discount)
		{
			$params = array(
				'payment_method'=>$payment_method, 
				'shipping_method'=>$shipping_method,
				'cart_items'=>$cart_items,
				'shipping_address'=>$shipping_address,
				'subtotal'=>$subtotal,
				'cart_discount'=>$cart_discount
			);
			
			$conditions_root = Shop_PriceRuleConditionProxy::get_root_condition($this->id, 'cart_conditions');
			return $conditions_root->is_true($params);

//			return $this->get_conditions_root(null, true)->is_true($params);
		}

		public function eval_discount($cart_items, $current_subtotal, &$product_price_map, &$item_discount_tax_incl_map, $customer)
		{
			$params = array('cart_items'=>$cart_items, 'current_subtotal'=>$current_subtotal, 'customer'=>$customer);
			
			$product_conditions_root = Shop_PriceRuleConditionProxy::get_root_condition($this->id, 'products_conditions');
			return $this->get_action_obj()->eval_discount($params, $this, $product_price_map, $item_discount_tax_incl_map, $product_conditions_root);
		}
		
		public function is_per_product_action()
		{
			return $this->get_action_obj()->is_per_product_action();
		}
		
		public static function reset_rule_cache()
		{
			self::$active_rules = null;
		}

		/**
		* Returns an array of discount rules active for the supplied customer
		* @param $coupon string
		* @param $customer Shop_Customer object
		* @param $coupon_check boolean, optional (set to true to find rules for the specified coupon only)
		*/
		protected static function list_active_rules($coupon, $customer, $coupon_check = false)
		{
			if (self::$active_rules !== null && !$coupon_check)
				return self::$active_rules;
				
			$coupon = mb_strtolower(trim($coupon));

			$rules = self::create()->where('active=1');
			$rules->join('shop_coupons', 'shop_coupons.id=coupon_id');
			if($coupon_check)
				$rules->where('(shop_coupons.code=?)', $coupon);
			else
				$rules->where('(shop_coupons.code is null or shop_coupons.code=?)', $coupon);

			$rules = $rules->order('sort_order')->find_all();
			
			if ($customer)
				$customer_group_id = $customer->customer_group_id;
			else
				$customer_group_id = Shop_CustomerGroup::get_guest_group()->id;

			$result = array();
			$current_user_time = Phpr_Date::userDate(Phpr_DateTime::now());
			foreach ($rules as $rule)
			{
				if (!$rule->is_active_now($current_user_time))
					continue;

				if ($rule->max_coupon_uses && strlen($rule->coupon_code))
				{
					if (Shop_Coupon::get_order_number($rule->coupon_code) >= $rule->max_coupon_uses)
						continue;
				}

				if ($rule->max_customer_uses)
				{
					if ($rule->get_customer_usage_number($customer) >= $rule->max_customer_uses)
						continue;
				}

				$rule->define_form_fields('eval_discounts');
				
				/**
				 * Evaluate which customer groups the rule is applicable for
				 * If there are no groups specified in the rule configuration
				 * apply it to all existing customer groups
				 */
				$rule_group_ids = trim($rule->customer_group_ids_str);
				if (strlen($rule_group_ids))
				{
					$rule_groups = explode(',', $rule_group_ids);

					foreach ($rule_groups as &$group_id)
						$group_id = trim($group_id);

					if (!in_array($customer_group_id, $rule_groups))
						continue;
				}

				$result[] = $rule;
			}
			
			$event_params = array(
				'coupon' => $coupon,
				'customer' => $customer,
				'coupon_check' => $coupon_check,
				'results' => $result
			);
			$updated_results = Backend::$events->fire_event(array('name' => 'shop:onListActiveCartPriceRules', 'type' => 'update_result'), $result, $event_params);
			if(is_array($updated_results)){
				$result = $updated_results;
			}

			if($coupon_check)
				return $result;
				
			return self::$active_rules = $result;
		}
		
		/**
		* Returns the number of discount rules active for the specified customer using a specified coupon code.
		* Can be used to check if a coupon is available for a customer.
		* @param $coupon_code string
		* @param $customer Shop_Customer object
		* @return integer
		*/
		public static function count_active_coupon_rules($coupon_code, $customer)
		{
			return count(self::list_active_rules($coupon_code, $customer, true));
		}
		
		/**
		* Checks whether a coupon code can be used. Returns a detailed error message in case if the coupon
		* cannot be used.
		* @param $coupon_code string
		* @param $customer Shop_Customer object
		* @return mixed Returns TRUE if the coupon can be used. Returns error message (string) if the coupon cannot be used.
		*/
		public static function validate_coupon_code($coupon_code, $customer, $validate_expiration_only = false)
		{
			$coupon = Shop_Coupon::find_coupon($coupon_code);
			if (!$coupon)
				return Cms_Exception('A coupon with the specified code is not found');

			$rules = Shop_CartPriceRule::create()->where('active=1');
			$rules->join('shop_coupons', 'shop_coupons.id=coupon_id');
			$rules->where('(shop_coupons.code=?)', $coupon->code);
			$rules = $rules->order('sort_order')->find_all();

			$guest_group_id = Shop_CustomerGroup::get_guest_group()->id;
			$customer_group_id = $customer ? $customer->customer_group_id : $guest_group_id;

			$current_user_time = Phpr_Date::userDate(Phpr_DateTime::now());
			$timezone_name = ' ('.Phpr_Date::getUserTimezone()->getName().')';
			$active_rule_found = false;
			$error = 'The coupon cannot be used at this time.';
			foreach ($rules as $rule)
			{
				if (!$rule->is_active_now($current_user_time))
				{
					if ($rule->date_start && $rule->date_end)
					{
						if ($rule->date_start->compare($rule->date_end) === 0)
						{
							$error = 'This coupon is not active yet. You can use it on '.
								Phpr_Date::display($rule->date_start, '%c').
								$timezone_name.'.';
							continue;
						}

						if ($rule->date_start->compare($current_user_time) > 0)
						{
							$error = 'This coupon is not active yet. You can use it between '.
								Phpr_Date::display($rule->date_start, '%c').' and '.
								Phpr_Date::display($rule->date_end, '%c').
								$timezone_name.'.';
							continue;
						}

						if ($rule->date_end->compare($current_user_time) < 0)
						{
							$error = 'This coupon expired on '.
								Phpr_Date::display($rule->date_end, '%c').
								$timezone_name.'.';
							continue;
						}
					}

					if ($rule->date_start)
					{
						if ($rule->date_start->compare($current_user_time) > 0)
						{
							$error = 'This coupon is not active yet.  It can be used starting on '.
								Phpr_Date::display($rule->date_start, '%c').
								$timezone_name.'.';
							continue;
						}
					}

					if ($rule->date_end)
					{
						if ($rule->date_end->compare($current_user_time) < 0)
						{
							$error = 'This coupon expired on '.
								Phpr_Date::display($rule->date_end, '%c').
								$timezone_name.'.';
							continue;
						}
					}

					$error = 'This coupon cannot be used today.';

					continue;
				}

				if (!$validate_expiration_only)
				{
					if ($rule->max_coupon_uses)
					{
						if (Shop_Coupon::get_order_number($rule->coupon_code) >= $rule->max_coupon_uses)
						{
							$error = 'This coupon have exceeded the number of times it can be used.';
							continue;
						}
					}

					if ($rule->max_customer_uses)
					{
						if ($rule->get_customer_usage_number($customer) >= $rule->max_customer_uses)
						{
							if ($rule->max_customer_uses > 1)
								$error = 'You have already used this coupon '.$rule->max_customer_uses.' times.';
							else
								$error = 'You have already used this coupon.';

							continue;
						}
					}

					$rule->define_form_fields();

					$rule_group_ids = trim($rule->customer_group_ids_str);
					if (strlen($rule_group_ids))
					{
						$rule_groups = explode(',', $rule_group_ids);

						foreach ($rule_groups as &$group_id)
							$group_id = trim($group_id);

						if (!in_array($customer_group_id, $rule_groups))
						{
							$group_names = array();
							foreach ($rule_groups as $rule_group_id)
							{
								$group = Shop_CustomerGroup::find_by_id($rule_group_id);
								if ($group)
									$group_names[] = $group->name;
							}
							
							if ($customer_group_id == $guest_group_id && !in_array($customer_group_id, $rule_groups))
								$error = 'This coupon code is only available to registered users. Please log in to continue and use this coupon code.';
							elseif (!$group_names)
								$error = 'A coupon with the specified code is not found.';
							else
								$error = 'This coupon is only available to the following customer group(s): '.implode($group_names);

							continue;
						}
					}
				}

				$active_rule_found = true;
				break;
			}
			
			if ($active_rule_found)
				return true;
				
			return $error;
		}
		
		public function get_customer_usage_number($customer)
		{
			if (!$customer)
				return 0;

			if (self::$customer_usage_map === null)
			{
				$usage = Db_DbHelper::objectArray('select 
					customer_id, shop_order_applied_rules.shop_cart_rule_id as rule_id, count(*) as cnt 
					from shop_order_applied_rules, shop_orders
					where shop_orders.id=shop_order_applied_rules.shop_order_id
					group by shop_orders.customer_id, shop_order_applied_rules.shop_cart_rule_id');

				self::$customer_usage_map = array();

				foreach ($usage as $usage_info)
				{
					if (!array_key_exists($usage_info->customer_id, self::$customer_usage_map))
						self::$customer_usage_map[$usage_info->customer_id] = array();
						
					self::$customer_usage_map[$usage_info->customer_id][$usage_info->rule_id] = $usage_info->cnt;
				}
			}

			if (!isset(self::$customer_usage_map[$customer->id][$this->id]))
				return 0;

			return self::$customer_usage_map[$customer->id][$this->id];
		}

		/**
		 * Evaluates and returns discount generated by the shopping cart price rules. 
		 * The indicator of the free shipping is returned as a parameter.
		 * @param Shop_PaymentMethod $payment_method Specifies a payment method selected by the customer
		 * @param Shop_ShippingOption $shipping_method Specifies a shipping method selected by the customer
		 * @param array $cart_items Contains an array of shopping cart items (objects of the Shop_CartItem class)
		 * @param Shop_CheckoutAddressInfo $shipping_address Specifies a shipping address specified by the customer
		 * @param string $coupon Specified a coupon code provided by the customer
		 * @param Shop_Customer $customer Specifies a current customer
		 * @param float $subtotal Specifies the order subtotal, after applying the catalog price rules
		 * @return Shop_DiscountData An object containing discount information
		 */
		public static function evaluate_discount($payment_method, $shipping_method, $cart_items, $shipping_address, $coupon, $customer, $subtotal)
		{
			$cart_discount = 0;
			$cart_discount_incl_tax = 0;
			$free_shipping = false;
			$current_subtotal = $subtotal;
			$item_discount_map = array();
			$item_discount_tax_incl_map = array();
			$active_rules = array();
			$active_rules_info = array();
			$applied_rules = array();
			$applied_rules_info = array();
			$free_shipping_options = array();
			$shipping_discount = 0;
			$add_shipping_options = array();

			/**
			 * Prepare the discount maps
			 */

			foreach ($cart_items as $cart_item)
			{
				$item_discount_map[$cart_item->key] = 0;
				$item_discount_tax_incl_map[$cart_item->key] = 0;
			}

			/**
			 * Apply rules and calculate the discount value
			 */
			$rules = self::list_active_rules($coupon, $customer);

			$current_discount = 0;

			foreach ($rules as $rule)
			{
				if ($rule->is_actual_for($payment_method, $shipping_method, $cart_items, $shipping_address, $current_subtotal, $current_discount))
				{
					$discount = $rule->eval_discount($cart_items, $current_subtotal, $item_discount_map, $item_discount_tax_incl_map, $customer);
					$action = $rule->get_action_obj();
					if(isset($action->adds_shipping_option ) && $action->adds_shipping_option ){
						if($rule->shipping_option) {
							$add_shipping_options[$rule->shipping_option] = $rule->shipping_option;
						}
					}
					if(isset($action->shipping_discount) && $action->shipping_discount){
						$shipping_discount += $discount;
					} else {
						$current_subtotal -= $discount;
						$current_discount += $discount;
					}

					$active_rules[] = $rule->id;
					$rule_info = array('rule'=>$rule, 'discount'=>$discount, 'action' => $action);
					$active_rules_info[] = (object)$rule_info;

					if($rule->active && $action->has_applied()){
						$applied_rules[] = $rule->id;
						$applied_rules_info[] = (object)$rule_info;
					}

					$rule_free_shipping_options = $rule->free_shipping_options;
					if (!is_array($rule_free_shipping_options))
						$rule_free_shipping_options = array();

					foreach ($rule_free_shipping_options as $option)
						$free_shipping_options[$option] = 1;

					if ($rule->terminating && $discount > 0)
						break;
				}
			}

			foreach ($item_discount_map as $key=>&$value)
				$value = max(0, $value);
				
			/**
			 * Apply discounts to cart items
			 */

			foreach ($cart_items as $cart_item)
			{
				/*
				 * Calculate the total discount including tax
				 */
				$cart_discount_incl_tax += $item_discount_tax_incl_map[$cart_item->key]*$cart_item->quantity;

				/*
				 * Calculate the total discount excluding tax
				 */
				$cart_item->applied_discount = $item_discount_map[$cart_item->key];
				$cart_item->reset_de_cache();
				$cart_discount += $item_discount_map[$cart_item->key]*$cart_item->quantity;
			}

			$result = new Shop_DiscountData();
			$result->cart_discount = round($cart_discount, 2);
			$result->cart_discount_incl_tax = round($cart_discount_incl_tax, 2);
			$result->item_price_map = array();
			$result->applied_rules = $applied_rules;
			$result->active_rules = $active_rules;
			$result->applied_rules_info = $applied_rules_info;
			$result->active_rules_info = $active_rules_info;
			$result->free_shipping_options = $free_shipping_options;
			$result->shipping_discount = $shipping_discount;
			$result->add_shipping_options = $add_shipping_options;

			return $result;
		}


		/**
		 * Triggered after the active price rules are determined.
		 * This event can be used to filter out or alter the active rules by returning an updated result array.
		 * @event shop:onListActiveCartPriceRules
		 * @package shop.events
		 * @author Matt Manning (github:damanic)
		 * @result Array $result contains the active price rules.
		 * @params Array $params contains the parameters passed to list_active_rules() method.
		 * @return Array an updated $result array or null for no effect.
		 */
		private function event_onListActiveCartPriceRules($result, $params) {}

		/**
		 * Triggered before a discount is deleted.
		 * The event handler should accept two parameters - the price rule object and the price rule id.
		 * @event shop:onCartPriceRuleBeforeDelete
		 * @package shop.events
		 * @author Matt Manning (github:damanic)
		 * @param Shop_CartPriceRule $price_rule Specifies the price rule obj
		 * @param bool $rule_id Specifies the price rule id. If the identifier is not provided, deletes the current record.
		 */
		private function event_onCartPriceRuleBeforeDelete($price_rule, $price_rule_id=null) {}
	}
	
?>