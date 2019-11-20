<?

	class Shop_PriceRuleBase extends Db_ActiveRecord
	{
		protected $action_obj = null;

		protected $added_fields = array();
		public $fetched_data = array();

		public function get_action_obj()
		{
			$class_name = $this->action_class_name;
			if ($this->action_obj != null && get_class($this->action_obj) == $class_name)
				return $this->action_obj;

			if (!class_exists($class_name))
				throw new Phpr_SystemException('Price rule action class "'.$class_name.'" not found.');

			return $this->action_obj = new $class_name();
		}
		
		public function define_form_fields($context = null)
		{
			$this->get_action_obj()->build_config_form($this);

			if (!$this->is_new_record())
				$this->load_xml_data();
			else
			{
				$this->get_action_obj()->init_fields_data($this);
			}
		}
		
		protected function init_fields_data()
		{
			$this->get_action_obj()->init_fields_data($this);
		}
		
		public function add_custom_field($code, $title, $side = 'full', $type = db_text, $hidden = false)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();
			if (!$hidden)
				$form_field = $this->add_form_field($code, $side)->tab('Action');
			else
				$form_field = null;

			$this->added_fields[$code] = $form_field;
			
			return $form_field;
		}
		
		public function add_field($code, $title, $side = 'full', $type = db_text, $hidden = false)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();
			if (!$hidden)
				$form_field = $this->add_form_field($code, $side)->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state')->tab('Action');
			else
				$form_field = null;

			$this->added_fields[$code] = $form_field;

			return $form_field;
		}
		
		/**
		 * Throws validation exception on a specified field
		 * @param $field Specifies a field code (previously added with add_field method)
		 * @param $message Specifies an error message text
		 */
		public function field_error($field, $message)
		{
			$this->validation->setError($message, $field, true);
		}
		
		public function get_added_field_options($db_name)
		{
			$obj = $this->get_action_obj();
			$method_name = "get_{$db_name}_options";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
				
			return $obj->$method_name($this);
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$obj = $this->get_action_obj();
			$method_name = "get_{$db_name}_option_state";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
				
			return $obj->$method_name($key_value);
		}
		
		public function after_validation($deferred_session_key = null) 
		{
			$this->get_action_obj()->validate_settings($this);
		}

		protected function load_xml_data()
		{
			if (!strlen($this->action_xml_data))
				return;

			if (strlen($this->parameters_serialized) && Cms_Controller::get_instance())
			{
				try
				{
					$fields = @unserialize($this->parameters_serialized);
					if (is_array($fields))
					{
						foreach ($fields as $code=>$value)
						{
							$this->$code = $value;
							$this->fetched_data[$code] = $value;
						}

						return;
					}
				}
				catch (exception $ex) {}
			}

			$doc = new DOMDocument('1.0');
			$doc->loadXML($this->action_xml_data);
			$xPath = new DOMXPath($doc);

			$root_node = $xPath->query("//action");
			if ($root_node->length)
			{
				$root_node = $root_node->item(0);
				
				$fields = $xPath->query("field", $root_node);
				foreach ($fields as $field)
				{
					$value_node = $xPath->query("value", $field);
					$value = $value_node->item(0)->nodeValue;

					$id_node = $xPath->query("id", $field);
					$field_id = $id_node->item(0)->nodeValue;
					
					if (strlen($value) && substr($value, 0, 5) == '!SER!')
						$value = unserialize(substr($value, 5));
					
					$this->$field_id = $value;
					$this->fetched_data[$field_id] = $value;
				}
			}
		}
		
		public function get_action_xml_data()
		{
			$document = new SimpleXMLElement('<action></action>');

			foreach ($this->added_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$value = $this->$code;
				if (is_array($value))
					$value = '!SER!'.serialize($value);

				$field_element->addChild('value', $value);
			}
			
			return $document->asXML();
		}
		
		protected function serialize_action_data()
		{
			$fields = array();
			foreach ($this->added_fields as $code=>$form_field)
				$fields[$code] = $this->$code;
				
			return @serialize($fields);
		}

		public function before_save($deferred_session_key = null)
		{
			$this->action_xml_data = $this->get_action_xml_data();
			$this->parameters_serialized = $this->serialize_action_data();
		}
		
		public function set_orders($ids, $orders)
		{
			$table_name = $this->table_name;

			foreach ($ids as $index=>$id)
			{
				if (!isset($orders[$index]))
					continue;
				
				$order = $orders[$index];
				Db_DbHelper::query('update `'.$table_name.'` set sort_order=:order where id=:id', array(
					'order'=>$order,
					'id'=>$id
				));
			}
		}

		/**
		 * @deprecated Use {@link Shop_PriceRuleBase::is_active_now()} method.
		 */
		public function is_active_today()
		{
			$current_user_time = Phpr_Date::userDate(Phpr_DateTime::now());
			return $this->is_active_now($current_user_time);
		}

		/**
		 * Checks if rule is currently active at this time.
		 * @documentable
		 * @param Phpr_DateTime $user_datetime Optional, the users datetime to consider, otherwise system time is used.
		 * @return boolean Returns TRUE if active. Returns FALSE otherwise.
		 */
		public function is_active_now($user_datetime=null)
		{
			if (!$this->date_start && !$this->date_end)
				return true;

			$now = Phpr_DateTime::now(); //system time

			if($user_datetime && is_a($user_datetime,'Phpr_DateTime')){
				$now = $user_datetime; //users time
			}

			if ($this->date_start && $this->date_end)
				return $this->date_start->compare($now) <= 0 && $this->date_end->compare($now) >= 0;

			if ($this->date_start)
				return $this->date_start->compare($now) <= 0;

			if ($this->date_end)
				return $this->date_end->compare($now) >= 0;

			return false;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>