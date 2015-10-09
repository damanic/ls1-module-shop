<?

	class Shop_RuleConditionBase
	{
		const type_product = 'product';
		const type_cart_product_attribute = 'cart_product_attribute';
		const type_cart = 'cart';
		const type_cart_root = 'cart_root';
		const type_cart_attribute = 'cart_attribute';
		const type_any = 'any';
		
		protected static $condition_classes = null;
		protected static $untyped_condition_classes = null;
		
		public function get_text($parameters_host)
		{
			echo "Condition text";
		}
		
		/**
		 * Returns a condition name for displaying in the condition selection drop-down menu
		 */
		public function get_name()
		{
			return "Condition";
		}

		/**
		 * Returns a condition title for displaying in the condition settings form
		 */
		public function get_title($host_obj)
		{
			return "Condition";
		}

		public function build_config_form($host_obj)
		{
		}
		
		public function init_fields_data($host_obj)
		{
		}
		
		public function prepare_filter_model($host_obj, $model, $options)
		{
			return $model;
		}
		
		public function onPreRender($controller, $host_obj)
		{
		}

		public function validate_settings($host_obj)
		{
		}

		public function get_child_options($rule_type, $parent_ids)
		{
			return array();
		}
		
		public function process_config_form_event($process_config_form_event, $controller)
		{
			
		}
		
		/**
		 * This function should return one of the self::type_ constants 
		 * depending on a place where the condition is valid
		 */
		public function get_condition_type()
		{
			return self::type_any;
		}
		
		public function list_subconditions()
		{
			return array();
		}
		
		/**
		 * Returns a title to use for grouping subconditions 
		 * in the Create Condition drop-down menu
		 */
		public function get_grouping_title()
		{
			return null;
		}
		
		public static function find_conditions_by_type($type)
		{
			if (self::$condition_classes === null)
			{
				$class_path = PATH_APP."/modules/shop/price_rule_conditions";
				$iterator = new DirectoryIterator($class_path);
				foreach ($iterator as $file)
				{
					if (!$file->isDir() && preg_match('/^shop_[^\.]*\.php$/i', $file->getFilename()))
						require_once($class_path.'/'.$file->getFilename());
				}
				
				$modules = Core_ModuleManager::listModules();
				foreach ($modules as $module_id=>$module_info)
				{
					$class_path = PATH_APP."/modules/".$module_id."/classes";
					$iterator = new DirectoryIterator($class_path);

					foreach ($iterator as $file)
					{

						if (!$file->isDir() && preg_match('/^'.$module_id.'_de_[^\.]*\.php$/i', $file->getFilename()))
							require_once($class_path.'/'.$file->getFilename());
					}
				}

				$classes = get_declared_classes();
				self::$condition_classes = array();
				self::$untyped_condition_classes = array();
				foreach ($classes as $class)
				{
					if (preg_match('/_Condition$/i', $class))
					{
						$reflection = new ReflectionClass($class); 
						if ($reflection->isSubclassOf('Shop_RuleConditionBase'))
						{
							$obj = new $class();
							$condition_type = $obj->get_condition_type();
							if ($condition_type != self::type_any)
								self::$condition_classes[$condition_type][] = $class;
							else
								self::$untyped_condition_classes[] = $class;
						}
						
					}
				}
			}

			$result = array();
			if (array_key_exists($type, self::$condition_classes))
				$result = self::$condition_classes[$type];

			foreach (self::$untyped_condition_classes as $class)
				$result[] = $class;

			return $result;
		}
		
		public function set_custom_data($host_obj)
		{
		}

		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function is_true(&$params, $host_obj)
		{
			return false;
		}
	}

?>