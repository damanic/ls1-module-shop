<?php

	class Shop_PriceRuleConditionProxy
	{
		private $fields = array();
		private $key;
		private $condition_obj = null;
		
		private static $cache = false;

		public function __construct($fields)
		{
			$this->fields = $fields;
		}
		
		public function __get($field)
		{
			if ($field == 'children')
				return $this->get_children();
			
			if (array_key_exists($field, $this->fields))
				return $this->fields[$field];

			return false;
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
		
		public function is_true(&$params)
		{
			$this->load_data();
			return $this->get_condition_obj()->is_true($params, $this);
		}
		
		protected function load_data()
		{
			if (!strlen($this->xml_data))
				return;
				
			if (strlen($this->parameters_serialized))
			{
				try
				{
					$fields = @unserialize($this->parameters_serialized);
					if (is_array($fields))
					{
						foreach ($fields as $code=>$value)
							$this->fields[$code] = $value;

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
					
					$this->fields[$field_id] = $value;
				}
			}
		}
		
		protected function get_children()
		{
			if (!isset(self::$cache[$this->host_rule_set]))
				return new Db_DataCollection();
				
			if (!isset(self::$cache[$this->host_rule_set][$this->id]))
				return new Db_DataCollection();
				
			return new Db_DataCollection(self::$cache[$this->host_rule_set][$this->id]);
		}
		
		public static function get_root_condition($host_id, $rule_set)
		{
			self::init_cache();
			
			if (!isset(self::$cache[$rule_set]))
				return null;

			if (!isset(self::$cache[$rule_set][-1]))
				return null;
				
			foreach (self::$cache[$rule_set][-1] as $root_condition)
			{
				if ($root_condition->rule_host_id == $host_id)
					return $root_condition;
			}
			
			return null;
		}
		
		private static function init_cache()
		{
			if (self::$cache !== false)
				return;

			self::$cache = array();
			
			$data = Db_DbHelper::queryArray('select * from shop_price_rule_conditions');
			foreach ($data as $condition_data)
			{
				if (!isset(self::$cache[$condition_data['host_rule_set']]))
					self::$cache[$condition_data['host_rule_set']] = array();
					
				$parent_key = $condition_data['rule_parent_id'] ? $condition_data['rule_parent_id'] : -1;
				self::$cache[$condition_data['host_rule_set']][$parent_key][] = new self($condition_data);
			}
		}
		
		public static function reset_cache()
		{
			self::$cache = false;
		}
	}

?>