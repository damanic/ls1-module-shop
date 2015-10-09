<?php

	class Shop_CatalogPriceRule extends Shop_PriceRuleBase
	{
		public $table_name = 'shop_catalog_rules';

		public $has_and_belongs_to_many = array(
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_catalog_rules_customer_groups', 'order'=>'name')
		);
		
		public $has_many = array(
			'rule_conditions'=>array(
				'class_name'=>'Shop_PriceRuleCondition',
				'foreign_key'=>'rule_host_id',
				'conditions'=>"host_rule_set='conditions' and rule_parent_id is null")
		);
		
		public $custom_columns = array('rule_conditions_field'=>db_text);
		
		protected static $customer_groups = null;
		protected static $active_rules = null;
		protected static $rule_cache = array();
		protected $conditions_root = false;

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
			$this->define_column('date_start', 'From Date')->validation();
			$this->define_column('date_end', 'To Date')->validation();
			$this->define_column('terminating', 'Terminating Rule');
			$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', '@name');
			$this->define_multi_relation_column('customer_group_ids', 'customer_groups', 'Customer Group Ids', '@id');
			$this->define_column('rule_conditions_field', 'Conditions');
			$this->define_column('action_class_name', 'Action');
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
			$this->add_form_field('active')->tab('Rule Settings');
			$this->add_form_field('name')->tab('Rule Settings');
			$this->add_form_field('description')->tab('Rule Settings')->size('tiny');
			$this->add_form_field('date_start', 'left')->tab('Rule Settings');
			$this->add_form_field('date_end', 'right')->tab('Rule Settings');
			$this->add_form_field('customer_groups')->tab('Rule Settings')->comment('Please select customer groups the rule is enabled for. Do not select any group to make the rule enabled for all customer groups.', 'above');
			
			$this->add_form_field('rule_conditions_field')->tab('Conditions');
			$this->add_form_field('terminating')->comment('Stop processing other rules for the same products, if this rule took effect.')->tab('Action');
			$this->add_form_field('action_class_name')->tab('Action')->renderAs(frm_dropdown);
			
			$this->form_tab_css_class('Conditions', 'conditions_tab');
			
			if (!strlen($this->action_class_name))
			{
				$actions = $this->get_action_class_name_options();
				$action_classes = array_keys($actions);
				$this->action_class_name = $action_classes[0];
			}

			parent::define_form_fields($context);
		}

		public function get_action_class_name_options($key = -1)
		{
			$result = array();
			$actions = Shop_RuleActionBase::find_actions_by_type(Shop_RuleActionBase::type_product);
			
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
			Db_DbHelper::query('update shop_catalog_rules set sort_order=:sort_order where id=:id', array(
				'sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->sort_order = $this->id;
		}

		public function init_conditions($session_key)
		{
			$root_rule = new Shop_PriceRuleCondition();
			$root_rule->host_rule_set = 'conditions';
			$root_rule->class_name = 'Shop_RuleIfCondition';
			$root_rule->define_form_fields();
			$root_rule->save();

			$this->rule_conditions->add($root_rule, $session_key);
		}
		
		public function get_conditions_root($form_session_key = null, $allow_caching = false)
		{
			if ($this->conditions_root !== false && $allow_caching)
				return $this->conditions_root;
			
			$root_conditions = $this->list_related_records_deferred('rule_conditions', $form_session_key);
			if ($root_conditions->count)
				return $this->conditions_root = $root_conditions[0];

			return $this->conditions_root = null;
		}

		public function is_actual_for($product, $current_product_price, $option_matrix_record = null)
		{
			$params = array('product'=>$product, 'current_price'=>$current_product_price, 'om_record'=>$option_matrix_record);
			return $this->get_conditions_root(null, true)->is_true($params);
		}
		
		public function eval_price($product, $current_product_price)
		{
			$params = array('product'=>$product, 'current_price'=>$current_product_price);
			return $this->get_action_obj()->eval_amount($params, $this);
		}
		
		public static function process_products_batch($ids_str, $direct_call = false)
		{
			@set_time_limit(3600);

			if (!isset($_SERVER['HTTP_LS_SUBQUERY']) && !$direct_call)
				throw new Phpr_ApplicationException('Only authorized LemonStand users can perform this operation.');

			$raw_ids = explode(',', $ids_str);
			$ids = array();
			foreach ($raw_ids as $id)
			{
				$id = trim($id);
				if (strlen($id))
					$ids[] = $id;
			}
			
			if (!$ids)
				return;
			
			$products = new Shop_Product(null, array('no_column_init'=>true, 'no_validation'=>true));
			$products = $products->where('shop_products.id in (?)', array($ids))->find_all();

			foreach ($products as $product)
				self::apply_rules_to_product($product);
		}
		
		public static function process_product_om_batch($ids_str, $direct_call = false)
		{
			@set_time_limit(3600);

			if (!isset($_SERVER['HTTP_LS_SUBQUERY']) && !$direct_call)
				throw new Phpr_ApplicationException('Only authorized LemonStand users can perform this operation.');

			$raw_ids = explode(',', $ids_str);
			$ids = array();
			foreach ($raw_ids as $id)
			{
				$id = trim($id);
				if (strlen($id))
					$ids[] = $id;
			}
			
			if (!$ids)
				return;

			$records = new Shop_OptionMatrixRecord(null, array('no_column_init'=>true, 'no_validation'=>true));
			$records = $records->where('id in (?)', array($ids))->find_all();

			foreach ($records as $record)
				self::apply_rules_to_product_om_record($record);
		}
		
		public static function apply_price_rules($product_id = null)
		{
			$counter = 0;
			
			try
			{
				@set_time_limit(3600);
				
				if ($product_id == null)
				{
					$product_ids = Db_DbHelper::scalarArray('select id from shop_products where ((grouped is null or grouped=0) or (grouped=1 and product_id is not null))');
					$total_product_count = count($product_ids);

					$batch_size = Phpr::$config->get('CATALOG_RULES_PROCESS_BATCH_SIZE', 50);

					$offset = 0;
					do
					{
						$batch_ids = array_slice($product_ids, $offset, $batch_size);
						if ($batch_ids)
						{
							$offset += $batch_size;
							$counter += count($batch_ids);

							$ids_str = implode(',', $batch_ids);

							if (!Phpr::$config->get('DISABLE_HTTP_SUBREQUESTS'))
							{
								$response = Core_Http::sub_request('/ls_shop_process_catalog_rules_batch', array('ids'=>$ids_str));
								if (!$response)
									throw new Phpr_ApplicationException('Unable to perform a sub-request.');

								if (trim($response) != 'SUCCESS')
									throw new Phpr_ApplicationException($response);

								Db_DbHelper::scalar('select 1');
							} else
								self::process_products_batch($ids_str, true);
						}
					} while ($batch_ids);

					$currentUser = Phpr::$security->getUser();
					$user_id = 'system';
					if ($currentUser)
						$user_id = $currentUser->id;

					$footprint = array(
						Phpr_DateTime::gmtNow()->format(Phpr_DateTime::universalDateTimeFormat),
						$user_id
					);
					Db_ModuleParameters::set('shop', 'catalog_price_rules_footprint', $footprint);
				} else
				{
					$product = Shop_Product::create()->find($product_id);
					if (!$product)
						return;

					self::apply_rules_to_product($product);
					$counter++;

					foreach ($product->grouped_products_all as $grouped_product)
					{
						self::apply_rules_to_product($grouped_product);
						$counter++;
					}
				}

				Shop_Module::update_catalog_version();
			} catch (Exception $ex)
			{
				throw new Phpr_SystemException('Error applying catalog price rules: '.$ex->getMessage());
			}
			
			return $counter;
		}
		
		protected static function apply_rules_to_product_option_matrix($product)
		{
			$counter = 0;
			
			try
			{
				@set_time_limit(3600);
				
				$record_ids = Db_DbHelper::scalarArray('select id from shop_option_matrix_records where product_id=:product_id', array('product_id'=>$product->id));
				$batch_size = Phpr::$config->get('CATALOG_RULES_PROCESS_BATCH_SIZE', 50);

				$offset = 0;
				do
				{
					$batch_ids = array_slice($record_ids, $offset, $batch_size);
					if ($batch_ids)
					{
						$offset += $batch_size;
						$counter += count($batch_ids);

						$ids_str = implode(',', $batch_ids);

						if (!Phpr::$config->get('DISABLE_HTTP_SUBREQUESTS'))
						{
							$response = Core_Http::sub_request('/ls_shop_process_catalog_rules_om_batch', array('ids'=>$ids_str));
							if (!$response)
								throw new Phpr_ApplicationException('Unable to perform a sub-request.');

							if (trim($response) != 'SUCCESS')
								throw new Phpr_ApplicationException($response);

							Db_DbHelper::scalar('select 1');
						} else
							self::process_product_om_batch($ids_str, true);
					}
				} while ($batch_ids);

			} catch (Exception $ex)
			{
				throw new Phpr_SystemException('Error applying catalog price rules: '.$ex->getMessage());
			}
			
			return $counter;
		}
		
		public static function get_footprint_info()
		{
			$footprint_info = Db_ModuleParameters::get('shop', 'catalog_price_rules_footprint');
			if (!$footprint_info)
				return null;
				
			if (!is_array($footprint_info))
				return null;
			
			$user_id = $footprint_info[1];
			$time = $footprint_info[0];

			$result = array();
			try
			{
				$time_obj = new Phpr_DateTime($time);
				$result['time'] = Phpr_Date::userDate($time_obj);

				if ($user_id != 'system')
				{
					$user_obj = Users_User::create()->find($user_id);
					if ($user_obj)
						$result['username'] = $user_obj->name;
					else
						$result['username'] = 'unknown user';
				} else
					$result['username'] = 'the system';
			}
			catch (Exception $ex)
			{
				return null;
			}
			
			return (object)$result;
		}
		
		/**
		 * This method is required for unit testing.
		 */
		public static function reset_active_rules_cache()
		{
			self::$active_rules = null;
		}
		
		protected static function list_customer_groups()
		{
			if (self::$customer_groups !== null)
				return self::$customer_groups;

			return self::$customer_groups = Shop_CustomerGroup::create()->find_all()->as_mapped_array();
		}
		
		protected static function list_active_rules()
		{
			if (self::$active_rules !== null)
				return self::$active_rules;

			$rules = self::create()->where('active=1')->order('sort_order')->find_all();

			self::$active_rules = array();
			foreach ($rules as $rule)
			{
				if (!$rule->is_active_today())
					continue;

				$rule->define_form_fields();

				self::$active_rules[] = $rule;
			}

			return self::$active_rules;
		}

		protected static function apply_rules_to_product($product)
		{
			$rules = self::list_active_rules();
			$groups = self::list_customer_groups();

			$product_price_map = array();
			$product_rule_map = array();
			$terminated_tiers = array();

			foreach ($rules as $rule)
			{
				/**
				 * Evaluate which customer groups the rule is applicable for
				 * If there are no groups specified in the rule configuration
				 * apply it to all existing customer groups
				 */
				$rule_group_ids = trim($rule->columnValue('customer_group_ids'));
				if (strlen($rule_group_ids))
				{
					$rule_groups = explode(',', $rule_group_ids);

					foreach ($rule_groups as &$group_id)
						$group_id = trim($group_id);
				} else
					$rule_groups = array_keys($groups);

				/**
				 * Apply rules for the evaluated customer groups
				 */
				foreach ($rule_groups as $role_group_id)
				{
					if (!array_key_exists($role_group_id, $groups))
						continue;

					if (in_array($role_group_id, $terminated_tiers))
						continue;

					$product_price_tiers = $product->list_group_price_tiers($role_group_id);
					foreach ($product_price_tiers as $tier_quantity=>$tier_price)
					{
						$current_map_price = $tier_price;
						$map_price = self::extract_price_from_the_map($product_price_map, $role_group_id, $tier_quantity);
						if ($map_price !== null)
							$current_map_price = $map_price;

						if ($rule->is_actual_for($product, $current_map_price))
						{
							$new_map_price = $rule->eval_price($product, $current_map_price);
							$product_price_map[$role_group_id][$tier_quantity] = $new_map_price;
							
							if (!array_key_exists($role_group_id, $product_rule_map))
								$product_rule_map[$role_group_id] = array();

							if (!in_array($rule->id, $product_rule_map[$role_group_id]))
								$product_rule_map[$role_group_id][] = $rule->id;
							
							if ($rule->terminating)
								$terminated_tiers[] = $role_group_id;
						}
					}
				}
			}
			
			self::apply_rules_to_product_option_matrix($product);

			$product->set_compiled_price_rules($product_price_map, $product_rule_map);
		}
		
		protected static function apply_rules_to_product_om_record($record)
		{
			$rules = self::list_active_rules();
			$groups = self::list_customer_groups();

			$product_price_map = array();
			$product_rule_map = array();
			$terminated_tiers = array();
			
			$product = Shop_Product::find_by_id($record->product_id);

			foreach ($rules as $rule)
			{
				/**
				 * Evaluate which customer groups the rule is applicable for
				 * If there are no groups specified in the rule configuration
				 * apply it to all existing customer groups
				 */
				$rule_group_ids = trim($rule->columnValue('customer_group_ids'));
				if (strlen($rule_group_ids))
				{
					$rule_groups = explode(',', $rule_group_ids);

					foreach ($rule_groups as &$group_id)
						$group_id = trim($group_id);
				} else
					$rule_groups = array_keys($groups);

				/**
				 * Apply rules for the evaluated customer groups
				 */
				foreach ($rule_groups as $role_group_id)
				{
					if (!array_key_exists($role_group_id, $groups))
						continue;

					if (in_array($role_group_id, $terminated_tiers))
						continue;

					$record_price_tiers = $record->list_group_price_tiers($product, $role_group_id);
					foreach ($record_price_tiers as $tier_quantity=>$tier_price)
					{
						$current_map_price = $tier_price;
						$map_price = self::extract_price_from_the_map($product_price_map, $role_group_id, $tier_quantity);
						if ($map_price !== null)
							$current_map_price = $map_price;

						if ($rule->is_actual_for($product, $current_map_price, $record))
						{
							$new_map_price = $rule->eval_price($product, $current_map_price);
							$product_price_map[$role_group_id][$tier_quantity] = $new_map_price;

							if (!array_key_exists($role_group_id, $product_rule_map))
								$product_rule_map[$role_group_id] = array();

							if (!in_array($rule->id, $product_rule_map[$role_group_id]))
								$product_rule_map[$role_group_id][] = $rule->id;
							
							if ($rule->terminating)
								$terminated_tiers[] = $role_group_id;
						}
					}
				}
			}

			$record->set_compiled_price_rules($product_price_map, $product_rule_map);
		}
		
		protected static function extract_price_from_the_map(&$price_map, $group_id, $quantity)
		{
			if (!array_key_exists($group_id, $price_map))
				return null;

			if (!array_key_exists($quantity, $price_map[$group_id]))
				return null;
				
			return $price_map[$group_id][$quantity];
		}
		
		public static function find_rule_by_id($id)
		{
			if (!array_key_exists($id, self::$rule_cache))
				self::$rule_cache[$id] = self::create()->find($id);
				
			return self::$rule_cache[$id];
		}
	}
	
?>