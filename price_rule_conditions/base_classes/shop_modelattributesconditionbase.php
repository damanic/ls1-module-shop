<?

	class Shop_ModelAttributesConditionBase extends Shop_RuleConditionBase
	{
		protected $model_class = '';
		
		protected $operators = array(
			'is'=>'is',
			'is_not'=>'is not',
			'equals_or_greater'=>'equals or greater than',
			'equals_or_less'=>'equals or less than',
			'contains'=>'contains',
			'does_not_contain'=>'does not contain',
			'greater'=>'greater than',
			'less'=>'less than',
			'one_of'=>'is one of',
			'not_one_of'=>'is not one of'
		);
		
		protected $model_obj = null;
		protected $reference_info = null;
		protected $model_attributes = null;
		protected static $model_obj_cache = array();
		protected static $attribute_control_type_cache = array();
		
		public function get_text($parameters_host)
		{
			$attributes = $this->list_model_attributes();

			if (isset($attributes[$parameters_host->subcondition]))
				$result = $this->get_condition_text_prefix($parameters_host, $attributes);
			else
				$result = 'Unknown attribute';

			if (isset($this->operators[$parameters_host->operator]))
				$result .= ' <span class="operator">'.h($this->operators[$parameters_host->operator]).'</span> ';
			else
				$result .= ' <span class="operator">'.$parameters_host->operator.'</span> ';

			$control_type = $this->get_value_control_type($parameters_host);
			if ($control_type == 'text')
			{
				$result .= $parameters_host->value;
			} else
			{
				$text_value = $this->get_custom_text_value($parameters_host);
				if ($text_value !== false)
					return $result.' '.$text_value;
				
				$reference_info = $this->prepare_reference_list_info($parameters_host);
				$class_name = get_class($reference_info->reference_model);
				$model_obj = new $class_name();

				if (!count($reference_info->columns))
					return $result;

				if (!strlen($parameters_host->value))
					return $result .= '?';
					
				$visible_field = $reference_info->columns[0];

				if ($control_type == 'dropdown')
				{
					$obj = $model_obj->where('id = ?', $parameters_host->value)->find();
					if ($obj)
						$result .= h($obj->$visible_field);
				} else {
					$ids = explode(',', $parameters_host->value);
					foreach ($ids as &$id)
						$id = trim(h($id));

					$records = $model_obj->where('id in (?)', array($ids))->order($visible_field)->find_all();
					$record_names = array();
					foreach ($records as $record)
						$record_names[] = $record->$visible_field;

					$result .= '('.implode(', ', $record_names).')';
				}
			}
			
			return $result;
		}

		public function init_fields_data($host_obj)
		{
			$host_obj->operator = 'is';
		}

		protected function get_condition_text_prefix($parameters_host, $attributes)
		{
			return $attributes[$parameters_host->subcondition];
		}
		
		public function build_config_form($host_obj)
		{
			$host_obj->add_field('subcondition', 'Attribute', 'left')->renderAs('attribute');
			$host_obj->add_field('operator', 'Operator', 'right')->renderAs('operator');
			$host_obj->add_field('value', 'Value')->renderAs('value')->cssClassName('text')->validation()->fn('trim');
		}
		
		public function get_subcondition_options($host_obj = null)
		{
			return $this->list_model_attributes();
		}

		public function get_operator_options($host_obj = null)
		{
			$options = array();
			$attribute = $host_obj->subcondition;

			$current_operator_value = $host_obj->operator;

			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();

			if (!isset($definitions[$attribute]))
			{
				$options = array('none'=>'Unknown attribute selected');
			}
			else
			{
				$column_type = $definitions[$attribute];

				if (!$column_type->isReference)
				{
					if ($column_type->type == db_varchar || $column_type->type == db_text)
					{
						$options = array(
							'is'=>'is',
							'is_not'=>'is not',
							'contains'=>'contains',
							'does_not_contain'=>'does not contain'
						);
					} else {
						$options = array(
							'is'=>'is',
							'is_not'=>'is not',
							'equals_or_greater'=>'equals or greater than',
							'equals_or_less'=>'equals or less than',
							'greater'=>'greater than',
							'less'=>'less than'
						);
					}
				} else {
					$options = array(
						'is'=>'is',
						'is_not'=>'is not',
						'one_of'=>'is one of',
						'not_one_of'=>'is not one of'
					);
				}
			}
			
			if (!array_key_exists($current_operator_value, $options))
			{
				$keys = array_keys($options);
				if (count($keys))
					$host_obj->operator = $options[$keys[0]];
				else
					$host_obj->operator = null;
			}

			return $options;
		}
		
		public function get_custom_text_value($parameters_host)
		{
			return false;
		}
		
		public function get_value_control_type($host_obj)
		{
			$attribute = $host_obj->subcondition;
			
			if (!Cms_Controller::get_instance())
				return $this->eval_control_type($host_obj);

			$control_type = $host_obj->condition_control_type;

			if ($control_type)
				return $control_type;

			$result = $this->eval_control_type($host_obj);

			Db_DbHelper::query('update shop_price_rule_conditions set condition_control_type=:control_type where id=:id', array(
				'control_type'=>$result,
				'id'=>$host_obj->id
			));
			
			$host_obj->condition_control_type = $result;
			
			return $result;
		}
		
		protected function eval_control_type($host_obj)
		{
			$attribute = $host_obj->subcondition;
			$operator = $host_obj->operator;

			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();

			if (!isset($definitions[$attribute]))
				return 'text';
				
			$column_type = $definitions[$attribute];
			if (!$column_type->isReference)
				return 'text';
			else 
			{

				if ($operator == 'is' || $operator == 'is_not')
					return 'dropdown';
					
				return 'multi_value';
			}
		}
		
		public function set_custom_data($host_obj)
		{
			$host_obj->condition_control_type = $this->eval_control_type($host_obj);
		}
		
		public function process_config_form_event($host_obj, $controller)
		{
			$post_data = post('Shop_PriceRuleCondition', array());

			$host_obj->subcondition = $post_data['subcondition'];
			$host_obj->operator = isset($post_data['operator']) ? $post_data['operator'] : null;
			$host_obj->value = isset($post_data['value']) ? $post_data['value'] : null;

			$new_control_type = $this->get_value_control_type($host_obj);
			$prev_control_type = post('prev_control_type');
			
			$prev_attribute = post('prev_attribute');

			$keep_value = false;
			if ($new_control_type == $prev_control_type && $prev_control_type == 'text')
				$keep_value = true;
			elseif ($new_control_type == $prev_control_type && $prev_attribute == $host_obj->subcondition)
				$keep_value = true;
				
			if (!$keep_value)
				$host_obj->value = null;

			if (post('type') == 'attribute_change')
			{
				echo ">>form_field_container_operatorShop_PriceRuleCondition<<";
				$controller->formRenderFieldContainer($host_obj, 'operator');
				
				echo ">>form_field_container_valueShop_PriceRuleCondition<<";
				$controller->formRenderFieldContainer($host_obj, 'value');
			} elseif (post('type') == 'operator_change')
			{
				echo ">>form_field_container_valueShop_PriceRuleCondition<<";
				$controller->formRenderFieldContainer($host_obj, 'value');
			}
		}

		public function get_condition_type()
		{
			return Shop_RuleConditionBase::type_product;
		}

		public function get_model_obj()
		{
			if ($this->model_obj === null)
			{
				if (array_key_exists($this->model_class, self::$model_obj_cache))
					$this->model_obj = self::$model_obj_cache[$this->model_class];
				else
					$this->model_obj = self::$model_obj_cache[$this->model_class] = new $this->model_class();
			}
				
			return $this->model_obj;
		}
		
		protected function list_model_attributes()
		{
			if ($this->model_attributes)
				return $this->model_attributes;
			
			$attributes = $this->get_model_obj()->get_condition_attributes();
			asort($attributes);

			return $this->model_attributes = $attributes;
		}
		
		public function list_subconditions()
		{
			$attributes = $this->list_model_attributes();
			$result = array();
			foreach ($attributes as $name=>$code)
				$result[$code] = $name;

			return $result;
		}
		
		public function get_value_dropdown_options($host_obj, $controller)
		{
			$attribute = $host_obj->subcondition;
			
			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();

			$model->define_form_fields();

			if (!isset($definitions[$attribute]))
				return array();

			$column_type = $definitions[$attribute];
			if ($column_type->referenceType != 'has_and_belongs_to_many')
			{
				$options = $controller->formFieldGetOptions($attribute, $model);
				$result = array();
				foreach ($options as $id=>$name)
					$result[$id] = h($name);
					
				return $result;
			}
			else
			{
				$options = $controller->formFieldGetOptions($attribute, $model);

				$result = array();
				foreach ($options as $id=>$params)
				{
					$result[$id] = str_repeat('&nbsp;&nbsp;&nbsp;', $params[2]).h($params[0]);
				}

				return $result;
			}
		}
		
		public function list_selected_reference_records($host_obj)
		{
			$reference_info = $this->prepare_reference_list_info($host_obj);
			$model_class = get_class($reference_info->reference_model);
			
			$order_field = $reference_info->columns[0];

			$keys = explode(',', $host_obj->value);
			$model_obj = new $model_class();
			
			if (count($keys))
				$model_obj->where('id in (?)', array($keys));
			else
				$model_obj->where('id <> id');
				
			return $model_obj->order($order_field)->find_all();
		}
		
		public function prepare_reference_list_info($host_obj)
		{
			if (!is_null($this->reference_info))
				return $this->reference_info;
				
			$attribute = $host_obj->subcondition;

			$model = $this->get_model_obj();
			$definitions = $model->get_column_definitions();

			$has_primary_key = $has_foreign_key = false;
			$options = $model->get_relation_options($model->has_models[$attribute], $attribute, $has_primary_key, $has_foreign_key);

			$model_class = $options['class_name'];
			$model = new $model_class();

			$model->init_columns_info();
			$model_columns = $model->get_column_definitions();
			
			$visible_columns = $this->get_reference_visible_columns($model, $model_columns);
			if (!count($visible_columns))
				throw new Phpr_SystemException('Order column is not defined in the '.$model_class.' model.');
			
			$this->reference_info = array();
			$this->reference_info['reference_model'] = $model;
			$this->reference_info['columns'] = $visible_columns;
			
			return $this->reference_info = (object)$this->reference_info;
		}
		
		protected function get_reference_visible_columns($model, $model_columns)
		{
			$visible_columns = array();
			foreach ($model_columns as $db_name=>$column_definition)
			{
				if ($column_definition->defaultOrder)
					$visible_columns[] = $db_name;
			}
			
			return $visible_columns;
		}
		
		public function onPreRender($controller, $host_obj)
		{
			if (!post('multiselect_control'))
				return;
			
			$post_data = post('Shop_PriceRuleCondition', array());
			$attribute = $host_obj->subcondition = $post_data['subcondition'];
			$host_obj->operator = isset($post_data['operator']) ? $post_data['operator'] : null;

			$control_type = $this->get_value_control_type($host_obj);
			if ($control_type != 'multi_value')
				return;

			$reference_info = $this->prepare_reference_list_info($host_obj);

			$controller->list_custom_body_cells = PATH_APP.'/modules/shop/price_rule_conditions/base_classes/shop_modelattributesconditionbase/_multiselect_body_control.htm';
			$controller->list_custom_head_cells = PATH_APP.'/modules/shop/price_rule_conditions/base_classes/shop_modelattributesconditionbase/_multiselect_head_control.htm';
			
			$searchFields = $this->get_reference_search_fields($reference_info->reference_model, $reference_info->columns);

			$controller->list_options['list_model_class'] = get_class($reference_info->reference_model);
			$controller->list_options['list_no_setup_link'] = true;
			$controller->list_options['list_columns'] = $reference_info->columns;
			$controller->list_options['list_render_as_sliding_list'] = $reference_info->reference_model->isExtendedWith('Db_Act_As_Tree');
			$controller->list_options['list_custom_body_cells'] = $controller->list_custom_body_cells;
			$controller->list_options['list_custom_head_cells'] = $controller->list_custom_head_cells;
			$controller->list_options['list_search_fields'] = $searchFields;
			$controller->list_options['list_search_prompt'] = 'search';
			$controller->list_options['list_no_form'] = true;
			$controller->list_options['list_record_url'] = null;
			$controller->list_options['list_items_per_page'] = 6;
			$controller->list_options['list_search_enabled'] = true;
			$controller->list_options['list_name'] = 'condition_settings_mutiselect_'.$attribute;
			$controller->list_options['list_no_js_declarations'] = true;
			$controller->list_options['list_scrollable'] = false;
			$controller->list_options['list_sorting_column'] = $reference_info->columns[0];
			$controller->list_name = 'condition_settings_mutiselect';
			$controller->list_custom_prepare_func = 'conditionsPrepareMultiselectData';
			$controller->list_record_url = null;

			$controller->listApplyOptions($controller->list_options);
		}

		public function get_reference_search_fields($model, $columns)
		{
			foreach ($columns as $index=>&$field)
				$field = "@".$field;

			return $columns;
		}

		public function prepare_filter_model($host_obj, $model, $options)
		{
			return $model;
		}

		public function validate_settings($host_obj)
		{
			$value = trim($host_obj->value);
			if (!strlen($value))
				$host_obj->field_error('value', 'Please specify value');
		}

		/**
		 * Checks whether the condition is TRUE for a specified model
		 */
		public function eval_is_true($model, $host_obj, $custom_value = '__no_value_provided__')
		{
			$operator = $host_obj->operator;
			$attribute = $host_obj->subcondition;
			$condition_value = $host_obj->value;

			$condition_value = trim(mb_strtolower($condition_value));

			$control_type = $this->get_value_control_type($host_obj);
			if ($control_type == 'text')
			{
				if ($custom_value === '__no_value_provided__')
					$model_value = trim(mb_strtolower($model->$attribute));
				else
					$model_value = trim(mb_strtolower($custom_value));

				if ($operator == 'is')
					return $model_value == $condition_value;
					
				if ($operator == 'is_not')
					return $model_value != $condition_value;

				if ($operator == 'contains')
					return mb_strpos($model_value, $condition_value) !== false;

				if ($operator == 'does_not_contain')
					return mb_strpos($model_value, $condition_value) === false;
					
				if ($operator == 'equals_or_greater')
					return $model_value >= $condition_value;

				if ($operator == 'equals_or_less')
					return $model_value <= $condition_value;

				if ($operator == 'greater')
					return $model_value > $condition_value;

				if ($operator == 'less')
					return $model_value < $condition_value;
			}
			
			if ($control_type == 'dropdown')
			{
				if ($custom_value === '__no_value_provided__')
					$model_value = $model->$attribute;
				else
					$model_value = $custom_value;

				if ($operator == 'is')
				{
					if ($model_value == null)
						return false;

					if ($model_value instanceof Db_ActiveRecord)
						return $model_value->get_primary_key_value() == $condition_value;
						
					if (is_array($model_value) && count($model_value) == 1 && array_key_exists(0, $model_value))
					    return $model_value[0] == $condition_value;
						
					if ($model_value instanceof Db_DataCollection)
					{
						if ($model_value->count != 1)
							return false;

						return $model_value[0]->get_primary_key_value() == $condition_value;
					}
				}

				if ($operator == 'is_not')
				{
					if ($model_value == null)
						return true;

					if ($model_value instanceof Db_ActiveRecord)
						return $model_value->get_primary_key_value() != $condition_value;
						
					if (is_array($model_value))
					{
						if (count($model_value) != 1)
							return true;
							
						if (!array_key_exists(0, $model_value))
							return true;
							
						return $model_value[0] != $condition_value;
					}
						
					if ($model_value instanceof Db_DataCollection)
					{
						if (!$model_value->count || $model_value->count > 1)
							return true;

						return $model_value[0]->get_primary_key_value() != $condition_value;
					}
				}
			}

			if ($control_type == 'multi_value')
			{
				if ($custom_value === '__no_value_provided__')
					$model_value = $model->$attribute;
				else
					$model_value = $custom_value;

				if (!($model_value instanceof Db_DataCollection) && !($model_value instanceof Db_ActiveRecord) && !(is_array($model_value)))
					return false;

				if (strlen($condition_value))
				{
					$condition_values = explode(',', $condition_value);
					foreach ($condition_values as &$value)
						$value = trim($value);
				} else
					$condition_values = array();

				if ($model_value instanceof Db_DataCollection)
					$model_keys = array_keys($model_value->as_array('id', 'id'));
				elseif ($model_value instanceof Db_ActiveRecord)
					$model_keys = array($model_value->get_primary_key_value());
				else
				 	$model_keys = $model_value;
					
				if ($operator == 'is')
					$operator = 'one_of';
				elseif ($operator == 'is_not')
					$operator = 'not_one_of';

				if ($operator == 'one_of')
					return count(array_intersect($condition_values, $model_keys)) ? true : false;

				if ($operator == 'not_one_of')
					return count(array_intersect($condition_values, $model_keys)) ? false : true;
			}

			return false;
		}
	}

?>