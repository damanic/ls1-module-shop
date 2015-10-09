<?

	class Shop_CustomerCsvImportModel extends Backend_CsvImportModel
	{
		public $table_name = 'shop_customers';

		public $has_many = array(
			'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_CustomerCsvImportModel'", 'order'=>'id', 'delete'=>true),
			'config_import'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_CustomerCsvImportModel'", 'order'=>'id', 'delete'=>true)
		);

		public $belongs_to = array(
			'group'=>array('class_name'=>'Shop_CustomerGroup', 'foreign_key'=>'customer_group_id'),
			'default_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'billing_country_id')
		);

		public $custom_columns = array(
			'auto_create_groups'=>db_bool,
			'update_existing_emails'=>db_bool
		);

		public $auto_create_groups = true;

		protected $current_time = null;
		protected $existing_groups = null;
		protected $countries = array();
		protected $states = array();

		public function __construct($values = null, $options = array())
		{
			parent::__construct($values, $options);
		}

		public function define_columns($context = null)
		{
			parent::define_columns($context);

			$this->define_column('update_existing_emails', 'I want LemonStand to update customers with existing emails');
			$this->define_column('auto_create_groups', 'I want LemonStand to create customer groups specified in the CSV file');
			$this->define_relation_column('group', 'group', 'Customer Group ', db_varchar, '@name');
			
			$this->define_relation_column('default_country', 'default_country', 'Default country ', db_varchar, '@name')->validation()->required();
		}

		public function define_form_fields($context = null)
		{
			parent::define_form_fields($context);
			
			$this->add_form_field('update_existing_emails')->comment('If you leave this checkbox unchecked, LemonStand will skip customers with existing emails. Otherwise existing customers will be updated using information from the CSV file and other parameters specified on this page.', 'above', true);
			
			$this->add_form_field('default_country', 'left')->comment('This country will be used for customers with no country specified in the CSV file.', 'above', true)->emptyOption('<please select>');

			$this->add_form_field('auto_create_groups')->comment('You need to match the LemonStand customer <strong>Customer Group</strong> field to a CSV file column in order to use this feature. Otherwise please select a customer group for all imported customers in the list below.', 'above', true);
			$this->add_form_field('group')->comment('Please select a customer group for imported products', 'above')->cssClassName('expandable')->emptyOption('<please select>');
		}
		
		public function get_default_country_options($key_value=-1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_Country::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$records = Db_DbHelper::objectArray('select * from shop_countries order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}

		public function import_csv_data($data_model, $session_key, $column_map, $import_manager, $delimeter, $first_row_titles)
		{
			@set_time_limit(3600);

			/*
			 * Validate import configuration
			 */

			if ($this->auto_create_groups)
			{
				if (!$import_manager->csvImportDbColumnPresented($column_map, 'group'))
					throw new Phpr_ApplicationException('Please specify a matching column for the Customer Group customer field, or uncheck the "I want LemonStand to create customer groups specified in the CSV file" checkbox.');
			} else
			{
				if (!$this->group)
					throw new Phpr_ApplicationException('Please select a customer group.');
			}
			
			if (!$this->default_country)
				throw new Phpr_ApplicationException('Please select a default country.');

			$added = 0;
			$skipped = 0;
			$skipped_rows = array();
			$updated = 0;
			$errors = array();

			$csv_handle = $import_manager->csvImportGetCsvFileHandle();
			$column_definitions = $data_model->get_csv_import_columns();

			$first_row_found = false;
			$line_number = 0;
			while (($row = fgetcsv($csv_handle, 2000000, $delimeter)) !== FALSE)
			{
				$line_number++;

				if (Phpr_Files::csvRowIsEmpty($row))
					continue;
					
				if (!$first_row_found)
				{
					$first_row_found = true;

					if ($first_row_titles)
						continue;
				}

				try
				{
					$bind = array();
					$existing_customer_id = null;
					
					if (!$this->auto_create_groups)
						$bind['customer_group_id'] = $this->group->id;

					foreach ($column_map as $column_index=>$db_names)
					{
						if (!array_key_exists($column_index, $row))
							continue;
							
						$column_value = trim($row[$column_index]);

						foreach ($db_names as $db_name)
						{
							/*
							 * Skip unknown columns
							 */

							if (!array_key_exists($db_name, $column_definitions))
								continue;

							/*
							 * Find or update customer with existing email
							 */

							if ($db_name == 'email')
							{
								$existing_customer_id = $this->find_existing_customer_id($column_value);
								if ($existing_customer_id)
								{
									if (!$this->update_existing_emails)
									{
										/*
										 * Go to next row
										 */

										$skipped++;
										$skipped_rows[$line_number] = 'Existing email: '.$column_value;
										continue 3;
									}
								}
							}

							if ($column_definitions[$db_name]->type == db_bool)
							{
								$bind[$db_name] = Core_CsvHelper::boolean($column_value) ? '1' : '0';
							} else
							{
								if ($db_name == 'group')
								{
									$db_name = 'customer_group_id';
							
									if ($this->auto_create_groups)
										$column_value = $this->create_group($column_value);
									else
										continue;
								}
								
								$bind[$db_name] = $column_value;
							}
							
						}
					}

					$this->validate_fields($bind, $existing_customer_id);
					
					/*
					 * Create or update a customer record
					 */

					$customer_id = null;
					if ($existing_customer_id)
					{
						$customer_id = $this->update_customer_fields($existing_customer_id, $bind);
						$updated++;
					}
					else
					{
						$customer_id = $this->create_customer($bind);
						$added++;
					}

				} catch (Exception $ex)
				{
					$errors[$line_number] = $ex->getMessage();
				}
			}

			$result = array(
				'added'=>$added,
				'skipped'=>$skipped,
				'skipped_rows'=>$skipped_rows,
				'updated'=>$updated,
				'errors'=>$errors,
				'warnings'=>array()
			);

			return (object)$result;
		}
		
		protected function find_existing_customer_id($email)
		{
			if (!strlen($email))
				return null;

			return Db_DbHelper::scalar('select id from shop_customers where lower(email)=:email', array('email'=>mb_strtolower($email)));
		}
		
		protected function create_group($value)
		{
			if (!$this->existing_groups)
			{
				$existing_groups = Db_DbHelper::objectArray('select id, name from shop_customer_groups');
				$this->existing_groups = array();
				foreach ($existing_groups as $group)
					$this->existing_groups[trim(mb_strtolower($group->name))] = $group->id;
			}

			$group_name = trim($value);
			
			if (!strlen($group_name))
				return null;
			
			$key = mb_strtolower($group_name);
			if (array_key_exists($key, $this->existing_groups))
				return $this->existing_groups[$key];

			$customer_group = new Shop_CustomerGroup();
			$customer_group->name = $group_name;
			$customer_group->save();

			$this->existing_groups[$key] = $customer_group->id;
			return $this->existing_groups[$key];
		}

		protected function validate_fields(&$bind, $existing_customer = false)
		{
			if (!array_key_exists('first_name', $bind) || !strlen($bind['first_name']))
				throw new Phpr_ApplicationException('Customer first name is not specified');
				
			if (!array_key_exists('last_name', $bind) || !strlen($bind['last_name']))
				throw new Phpr_ApplicationException('Customer last name is not specified');

			if (!array_key_exists('email', $bind) || !strlen($bind['email']))
				throw new Phpr_ApplicationException('Customer email is not specified');

			if (!array_key_exists('billing_country', $bind) || !strlen($bind['billing_country']))
				$bind['billing_country'] = $this->default_country->name;

			if (!array_key_exists('billing_street_addr', $bind) || !strlen($bind['billing_street_addr']))
				$bind['billing_street_addr'] = null;

			if (!array_key_exists('billing_city', $bind) || !strlen($bind['billing_city']))
				$bind['billing_city'] = null;

			if (!array_key_exists('billing_zip', $bind) || !strlen($bind['billing_zip']))
				$bind['billing_zip'] = null;

			if (array_key_exists('password', $bind))
			{
				if (strlen($bind['password']))
					$bind['password'] = Phpr_SecurityFramework::create()->salted_hash($bind['password']);
				else
					unset($bind['password']);
			} elseif (!$existing_customer)
				$bind['password'] = Phpr_SecurityFramework::create()->salted_hash(uniqid('password', true));

			$bind['billing_country_id'] = $this->find_country($bind['billing_country']);
			
			if (array_key_exists('billing_state', $bind) && strlen($bind['billing_state']))
				$bind['billing_state_id'] = $this->find_state($bind['billing_country_id'], $bind['billing_state']);

			/*
			 * Copy billing fields to shipping, if needed
			 */
			
			if (!array_key_exists('shipping_first_name', $bind) || !strlen($bind['shipping_first_name']))
				$bind['shipping_first_name'] = $bind['first_name'];

			if (!array_key_exists('shipping_last_name', $bind) || !strlen($bind['shipping_last_name']))
				$bind['shipping_last_name'] = $bind['last_name'];

			if (!array_key_exists('shipping_country', $bind) || !strlen($bind['shipping_country']))
				$bind['shipping_country_id'] = $bind['billing_country_id'];
			else
				$bind['shipping_country_id'] = $this->find_country($bind['shipping_country']);

			if (!array_key_exists('shipping_state', $bind) || !strlen($bind['shipping_state']))
			{
				if (array_key_exists('billing_state_id', $bind) && strlen($bind['billing_state_id']))
					$bind['shipping_state_id'] = $bind['billing_state_id'];
			}
			else
				$bind['shipping_state_id'] = $this->find_state($bind['shipping_country_id'], $bind['shipping_state']);

			if (!array_key_exists('shipping_street_addr', $bind) || !strlen($bind['shipping_street_addr']))
				$bind['shipping_street_addr'] = $bind['billing_street_addr'];

			if (!array_key_exists('shipping_city', $bind) || !strlen($bind['shipping_city']))
				$bind['shipping_city'] = $bind['billing_city'];

			if (!array_key_exists('shipping_zip', $bind) || !strlen($bind['shipping_zip']))
				$bind['shipping_zip'] = $bind['billing_zip'];
				
			$unset_vars = array('billing_state', 'billing_country', 'shipping_state', 'shipping_country');
			foreach ($unset_vars as $var)
			{
				if (isset($bind[$var]))
					unset($bind[$var]);
			}
		}
		
		protected function find_country($country)
		{
			$key = trim(mb_strtolower($country));

			if (array_key_exists($key, $this->countries))
				return $this->countries[$key];
				
			$country_id = Db_DbHelper::scalar('select id from shop_countries where lower(name)=:country or lower(code)=:country or lower(code_3)=:country or code_iso_numeric=:country', array('country'=>$key));
			if (!$country_id)
				throw new Phpr_ApplicationException('Unknown country: '.$country);
				
			return $this->countries[$key] = $country_id;
		}
		
		protected function find_state($country_id, $state)
		{
			$key = trim(mb_strtolower($state));

			if (array_key_exists($key, $this->states))
				return $this->states[$key];
				
			$state_id = Db_DbHelper::scalar('select id from shop_states where (lower(name)=:state or lower(code)=:state) and country_id=:country_id', array('state'=>$key, 'country_id'=>$country_id));
			if (!$state_id)
				throw new Phpr_ApplicationException('Unknown state: '.$state);
				
			return $this->states[$key] = $state_id;
		}

		protected function update_customer_fields($existing_id, &$bind)
		{
			$fields = $bind;
			$fields['updated_at'] = $this->get_current_time();
			$fields['updated_user_id'] = null;

			Backend::$events->fireEvent('shop:onBeforeCsvCustomerUpdated', $fields, $existing_id);

			$user = Phpr::$security->getUser();
			if ($user)
				$fields['updated_user_id'] = $user->id;

			$this->sql_update('shop_customers', $fields, 'id='.$existing_id);
			
			Backend::$events->fireEvent('shop:onAfterCsvCustomerUpdated', $fields, $existing_id);
			
			return $existing_id;
		}
		
		protected function create_customer(&$bind)
		{
			$fields = $bind;
			$fields['created_at'] = $this->get_current_time();
			$fields['created_user_id'] = null;

			Backend::$events->fireEvent('shop:onBeforeCsvCustomerCreated', $fields);

			$user = Phpr::$security->getUser();
			if ($user)
				$fields['created_user_id'] = $user->id;
				
			$this->sql_insert('shop_customers', $fields);
			$id = Db_DbHelper::driver()->get_last_insert_id();
			
			Backend::$events->fireEvent('shop:onAfterCsvCustomerCreated', $fields, $id);
			
			return $id;
		}

		protected function get_current_time()
		{
			if ($this->current_time)
				return $this->current_time;

			return $this->current_time = Phpr_DateTime::now()->toSqlDateTime();
		}
	}

?>