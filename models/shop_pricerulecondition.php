<?php

	class Shop_PriceRuleCondition extends Db_ActiveRecord
	{
		const type_cart = 'cart';
		const type_catalog = 'catalog';
		const type_cart_products = 'cart_products';
		
		public $table_name = 'shop_price_rule_conditions';

		protected $condition_obj = null;
		protected $added_fields = array();
		public $fetched_data = array();
		public $custom_data_cache = null;
		
		protected $fields_defined = false;
		
		public $has_many = array(
			'children'=>array(
				'class_name'=>'Shop_PriceRuleCondition',
				'foreign_key'=>'rule_parent_id')
		);
		
		public static function create()
		{
			return new self();
		}
		
		public function define_form_fields($context = null)
		{
			if ($this->fields_defined)
				return;

			$this->fields_defined = true;

			$this->get_condition_obj()->build_config_form($this);

			$this->add_field('condition_text', 'text', 'full', db_text, true);
			
			if (!$this->is_new_record())
				$this->load_xml_data();
			else
			{
				$this->get_condition_obj()->init_fields_data($this);
			}
		}
		
		public function get_text()
		{
			return strlen($this->condition_text) ? $this->condition_text : $this->get_condition_obj()->get_text($this);
		}
		
		public function is_compound()
		{
			return $this->get_condition_obj() instanceof Shop_RuleCompoundConditionBase;
		}
		
		public function add_field($code, $title, $side = 'full', $type = db_text, $hidden = false)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();
			if (!$hidden)
				$form_field = $this->add_form_field($code, $side)->optionsMethod('get_added_field_options')->optionStateMethod('get_added_field_option_state');
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
			$obj = $this->get_condition_obj();
			$method_name = "get_{$db_name}_options";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
				
			return $obj->$method_name($this);
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$obj = $this->get_condition_obj();
			$method_name = "get_{$db_name}_option_state";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");
				
			return $obj->$method_name($key_value);
		}

		public function get_condition_obj()
		{
			if ($this->condition_obj != null)
				return $this->condition_obj;

			$class_name = $this->class_name;
			if (!class_exists($class_name))
				throw new Phpr_SystemException('Price rule condition class "'.$class_name.'" not found.');

			return $this->condition_obj = new $class_name();
		}

		protected function load_xml_data()
		{
			if (!strlen($this->xml_data))
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
				
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->loadXML($this->xml_data);
			$xPath = new DOMXPath($doc);

			$root_node = $xPath->query("//condition");
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
					
					$this->$field_id = $value;
					$this->fetched_data[$field_id] = $value;
				}
			}
		}
		
		public function get_xml_data()
		{
			$doc = new DOMDocument('1.0', 'UTF-8');
			$root_element = Core_Xml::create_dom_element($doc, $doc, 'condition');
			foreach ($this->added_fields as $code=>$form_field)
			{
				$field_element = Core_Xml::create_dom_element($doc, $root_element, 'field');
				Core_Xml::create_dom_element($doc, $field_element, 'id', $code);
				$value_element = Core_Xml::create_dom_element($doc, $field_element, 'value');
				Core_Xml::create_cdata($doc, $value_element, $this->$code);
			}

			return $doc->saveXML();
		}
		
		protected function serialize_condition_data()
		{
			$fields = array();
			foreach ($this->added_fields as $code=>$form_field)
				$fields[$code] = $this->$code;
				
			return @serialize($fields);
		}
		
		public function after_validation_on_update($deferred_session_key = null) 
		{
			$this->get_condition_obj()->validate_settings($this);
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->get_condition_obj()->set_custom_data($this);
			$this->xml_data = $this->get_xml_data();
			$this->parameters_serialized = $this->serialize_condition_data();
		}
		
		public function get_child_options($rule_type, $parent_ids)
		{
			return $this->get_condition_obj()->get_child_options($rule_type, $parent_ids);
		}
		
		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 */
		public function is_true(&$params)
		{
			$this->define_form_fields();
			return $this->get_condition_obj()->is_true($params, $this);
		}
	}

?>