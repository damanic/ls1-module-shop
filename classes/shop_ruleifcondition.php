<?

	class Shop_RuleIfCondition extends Shop_RuleCompoundConditionBase
	{
		public function get_join_text($parameters_host)
		{
			return $parameters_host->condition_type == 0 ? 'AND' : 'OR';
		}
		
		public function get_text($parameters_host)
		{
			$result = $parameters_host->condition_type == 0 ? 'ALL of subconditions should be ' : 'ANY of subconditions should be ';
			$result .= $parameters_host->condition == 'false' ? 'FALSE' : 'TRUE';
			
			return $result;
		}

		public function build_config_form($host_obj)
		{
			$host_obj->add_field('condition_type', 'Condition type', 'left')->renderAs(frm_dropdown);
			$host_obj->add_field('condition', 'Required value', 'right')->renderAs(frm_dropdown);
		}
		
		public function init_fields_data($host_obj)
		{
			$host_obj->condition_type = 0;
			$host_obj->condition = 'true';
		}
		
		public function get_condition_options($host_obj)
		{
			$options = array(
				'true'=>'TRUE',
				'false'=>'FALSE'
			);
			
			return $options;
		}

		public function get_condition_type_options($host_obj)
		{
			$options = array(
				'0'=>'ALL subconditions should meet the requirement',
				'1'=>'ANY subconditions should meet the requirement'
			);
			
			return $options;
		}

		/**
		 * Checks whether the condition is TRUE for specified parameters
		 * @param array $params Specifies a list of parameters as an associative array.
		 * For example: array('product'=>object, 'shipping_method'=>object)
		 * @param mixed $host_obj An object to load the action parameters from 
		 */
		public function is_true(&$params, $host_obj)
		{
			$required_condition_value = $host_obj->condition == 'true' ? true : false;

			foreach ($host_obj->children as $subcondition)
			{
				$subcondition_result = $subcondition->is_true($params)  ? true : false;

				if ($host_obj->condition_type == 0) // ALL
				{
					if ($subcondition_result !== $required_condition_value)
						return false;
				} else  // ANY
				{
					if ($subcondition_result === $required_condition_value)
						return true;
				}
			}
			
			if ($host_obj->condition_type == 0) // ALL
				return true;
			
			return false; 
		}
	}

?>