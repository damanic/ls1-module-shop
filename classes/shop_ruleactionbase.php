<?

	/**
	 * This is a base class for price rule actions
	 */
	class Shop_RuleActionBase
	{
		const type_product = 'product';
		const type_cart = 'cart';

		protected static $action_classes = null;
		
		/**
		 * This function should return one of the 'product' or 'cart' words, 
		 * depending on a place where the action is valid
		 */
		public function get_action_type()
		{
			return self::type_product;
		}

		/**
		 * Returns an action name for displaying in the action selection drop-down menu
		 */
		public function get_name()
		{
			return "Action";
		}

		public function build_config_form($host_obj)
		{
		}
		
		public function init_fields_data($host_obj)
		{
		}

		public function validate_settings($host_obj)
		{
		}

		public static function find_actions_by_type($type)
		{
			if (self::$action_classes === null)
			{
				$class_path = PATH_APP."/modules/shop/price_rule_actions";
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
				self::$action_classes = array();
				foreach ($classes as $class)
				{
					if (preg_match('/_Action$/i', $class))
					{
						$reflection = new ReflectionClass($class); 
						if ($reflection->isSubclassOf('Shop_RuleActionBase'))
						{
							$obj = new $class();
							$action_type = $obj->get_action_type();
							self::$action_classes[$action_type][] = $class;
						}
					}
				}
			}

			$result = array();
			if (array_key_exists($type, self::$action_classes))
				$result = self::$action_classes[$type];

			return $result;
		}

		/**
		 * Evaluates the product price. This method should be implemented only for product-type actions.
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function eval_amount(&$params, $host_obj)
		{
			return null;
		}
	}

?>