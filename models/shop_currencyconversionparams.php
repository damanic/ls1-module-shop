<?php

	class Shop_CurrencyConversionParams extends Db_ActiveRecord 
	{
		public $table_name = 'shop_currency_converter_params';
		public static $loadedInstance = null;
		protected $added_fields = array();
		public $fetched_data = array();
		
		protected $converter_obj = null;

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}

		public static function isConfigured()
		{
			$obj = self::get();
			if (!$obj)
				return false;

			return strlen($obj->class_name);
		}

		public function define_columns($context = null)
		{
			$this->define_column('class_name', 'Converter');
			$this->define_column('refresh_interval', 'Update Interval');
			$this->define_column('enable_cron_updates', 'Enable Cron Updates');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('class_name')->renderAs(frm_dropdown)->tab('Converter');
			$this->add_form_field('refresh_interval')->renderAs(frm_dropdown)->comment('Select update period of the internal currency rate cache.', 'above')->tab('Converter');

			$this->add_form_partial(PATH_APP.'/modules/shop/currency_converters/partials/_cron.htm')->tab('Converter');
			$this->add_form_field('enable_cron_updates')->renderAs(frm_onoffswitcher)->tab('Converter');


			$obj = $this->get_converter_object();
			if ($obj)
				$obj->build_config_ui($this);
				
			if (!$this->added_fields)
				$this->add_form_section('The selected currency converter has no additional configuration parameters.')->tab('Configuration');

			$this->load_xml_data();
		}
		
		public function add_field($code, $title, $side = 'full', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();

			$form_field = $this->add_form_field($code, $side)->optionsMethod('get_added_field_options');
			$form_field->tab('Configuration');
			
			$this->added_fields[$code] = $form_field;
			
			return $form_field;
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$obj = $this->get_converter_object();
			if ($obj)
			{
				$method_name = "get_{$db_name}_options";
				if (!method_exists($obj, $method_name))
					throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");

				return $obj->$method_name($current_key_value);
			}
		}
		
		public function get_converter_object()
		{
			if ($this->converter_obj !== null)
			 	return $this->converter_obj;
			
			if (!strlen($this->class_name))
				return null;

			$converters = Core_ModuleManager::findById('shop')->listCurrencyConverters();
			foreach ($converters as $class_name)
			{
				if ($this->class_name == $class_name)
					return $this->converter_obj = new $class_name();
			}
		}

		public function get_refresh_interval_options($key_value = -1)
		{
			$values = array(
				'1'=>'1 hour',
				'3'=>'3 hours',
				'6'=>'6 hours',
				'12'=>'12 hours',
				'24'=>'24 hours'
			);
			
			if ($key_value != -1)
				return array_key_exists($key_value, $values) ? $values[$values] : 24;

			return $values;
		}
		
		/**
		 * Throws validation exception on a specified field
		 * @param $field Specifies a field code (previously added with add_field method)
		 * @param $message Specifies an error message text
		 * @param $grid_row Specifies an index of grid row, for grid controls
		 * @param $grid_column Specifies a name of column, for grid controls
		 */
		public function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}

		public function before_save($deferred_session_key = null)
		{
			if (!$this->class_name)
				return;
			
			$obj = $this->get_converter_object();
			if ($obj)
				$obj->validate_config_on_save($this);

			$data = strlen($this->config_data) ? $this->config_data : '<?xml version="1.0"?><converter_settings></converter_settings>';
			
			$doc = new DOMDocument('1.0');
			$doc->loadXML($data);
			
			$xPath = new DOMXPath($doc);
			$converter_node = $xPath->query("//converter_settings/".$this->class_name);
			if ($converter_node->length)
				$doc->firstChild->removeChild($converter_node->item(0));
				
			$converter_node = Core_Xml::create_dom_element($doc, $doc->firstChild, $this->class_name);
			foreach ($this->added_fields as $code=>$form_field)
				Core_Xml::create_dom_element($doc, $converter_node, 'field', $this->$code)->setAttribute('id', $code);

			$this->config_data = $doc->saveXML();
		}
		
		protected function load_xml_data()
		{
			if (!strlen($this->config_data))
			{
				$obj = $this->get_converter_object();
				if ($obj)
					$obj->init_config_data($this);

				return;
			}

			$doc = new DOMDocument('1.0');
			$doc->loadXML($this->config_data);
			$xPath = new DOMXPath($doc);

			$converter_node = $xPath->query("//converter_settings/".$this->class_name);
			if ($converter_node->length)
			{
				$converter_node = $converter_node->item(0);
				
				$fields = $xPath->query("field", $converter_node);
				foreach ($fields as $field)
				{
					$field_id = $field->getAttribute('id');
					$value = $field->nodeValue;
					
					$this->$field_id = $value;
					$this->fetched_data[$field_id] = $value;
				}
			} else
			{
				$obj = $this->get_converter_object();
				if ($obj)
					$obj->init_config_data($this);
			}
		}
		
		public function get_class_name_options($key_value = -1)
		{
			$converters = Core_ModuleManager::findById('shop')->listCurrencyConverters();

			$converter_list = array();
			foreach ($converters as $converter)
			{
				$obj = new $converter();
				$info = $obj->get_info();
				$converter_list[$converter] = isset($info['name']) ? $info['name'] : 'Unknown converter';
			}
			
			if ($key_value != -1)
				return array_key_exists($key_value, $converter_list) ? $converter_list[$key_value] : 'Unknown converter';

			return $converter_list;
		}
	}
?>