<?php

	class Shop_RuleConditionsBehavior extends Phpr_ControllerBehavior
	{
		public $conditions_rule_type = Shop_PriceRuleCondition::type_catalog;

		public function __construct($controller)
		{
			parent::__construct($controller);
			
			$this->_controller->addCss('/modules/shop/behaviors/shop_ruleconditionsbehavior/resources/css/conditions.css?'.module_build('shop'));
			$this->_controller->addJavaScript('/modules/shop/behaviors/shop_ruleconditionsbehavior/resources/javascript/conditions.js?'.module_build('shop'));
			$this->_controller->addCss('/phproad/modules/db/behaviors/db_filterbehavior/resources/css/filters.css');
			$this->hideAction('conditionsRender');
			$this->hideAction('conditionsSetCache');
			$this->hideAction('conditionsGetCache');
			$this->hideAction('conditionsGetCollapseStatus');
			$this->hideAction('conditionsRregisterViewPaths');

			$this->addEventHandler('onLoadConditionSetup');
			$this->addEventHandler('onSaveCondition');
			$this->addEventHandler('onLoadConditionChildSelector');
			$this->addEventHandler('onCreateCondition');
			$this->addEventHandler('onConditionFormEvent');
			$this->addEventHandler('onCancelConditionSettings');
			$this->addEventHandler('onDeleteCondition');
			$this->addEventHandler('onSetCollapseStatus');

			if (post('current_condition_id'))
			{
				$condition = $this->findConditionObj();
				$condition_obj = $condition->get_condition_obj();
				$condition_obj->onPreRender($this->_controller, $condition);
			}
		}
		
		public function conditionsRender($model, $rule_set)
		{
			$this->viewData['pricerule_rule_set'] = $rule_set;
			$this->renderPartial('conditions');
		}
		
		public function conditionsRenderPartial($view, $params = array())
		{
			$this->renderPartial($view, $params);
		}
		
		protected function findConditionObj($condition_id = null)
		{
			$condition_id = $condition_id ? $condition_id : post('current_condition_id');
			$condition = null;
			if (strlen($condition_id))
				$condition = Shop_PriceRuleCondition::create()->find($condition_id);

			if (!$condition)
				throw new Phpr_ApplicationException('Condition not found');

			return $condition;
		}
		
		public function onLoadConditionSetup()
		{
			try
			{
				$condition = $this->findConditionObj();

				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['conditions_root_id'] = post('conditions_root_id');

				$data = $this->conditionsGetCache($condition);

				if ($data)
					$condition->xml_data = $data;

				$condition->define_form_fields();
				$this->viewData['condition'] = $condition;
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			if ($condition)
				$this->conditionsRregisterViewPaths($condition);

			$this->renderPartial('condition_settings_form');
		}
		
		public function conditionsRregisterViewPaths($condition)
		{
			$reflection_obj = new ReflectionClass($condition->class_name);
			$condition_file = $reflection_obj->getFileName();
			$this->_controller->formRegisterViewPath(dirname($condition_file).'/'.strtolower($condition->class_name));
			
			$reflection_parent = $reflection_obj->getParentClass();
			$condition_file = $reflection_parent->getFileName();
			$this->_controller->formRegisterViewPath(dirname($condition_file).'/'.strtolower($reflection_parent->getName()));
		}

		public function conditionsGetCache($condition, $default = null)
		{
			$cache = post('condition_data', array());
			if (array_key_exists($condition->id, $cache))
				return $cache[$condition->id];
				
			return $default;
		}

		public function conditionsSetCache($condition)
		{
			$cache = post('condition_data', array());
			$cache[$condition->id] = $condition->xml_data;
			$_POST['condition_data'] = $cache;
		}

		public function conditionsGetTextCache($condition, $default = null)
		{
			$cache = post('condition_text_data', array());
			if (array_key_exists($condition->id, $cache))
				return $cache[$condition->id];
				
			return $default;
		}

		public function conditionsSetTextCache($condition)
		{
			$cache = post('condition_text_data', array());
			$cache[$condition->id] = $condition->condition_text;
			$_POST['condition_text_data'] = $cache;
		}

		public function conditionsGetJoinTextCache($condition, $default = null)
		{
			$cache = post('condition_join_text_data', array());
			if (array_key_exists($condition->id, $cache))
				return $cache[$condition->id];
				
			return $default;
		}

		public function conditionsSetJoinTextCache($condition)
		{
			$cache = post('condition_join_text_data', array());
			$cache[$condition->id] = $condition->get_condition_obj()->get_join_text($condition);
			$_POST['condition_join_text_data'] = $cache;
		}

		public function onSaveCondition()
		{
			try
			{
				$condition = $this->findConditionObj();
				$condition->define_form_fields();

				$data = post(get_class($condition), array());
				$condition->validate_data($data);
				$condition->condition_text = $condition->get_condition_obj()->get_text($condition);
				$condition->xml_data = $condition->get_xml_data();

				$this->conditionsSetCache($condition);
				$this->conditionsSetTextCache($condition);
				if ($condition->is_compound())
					$this->conditionsSetJoinTextCache($condition);

				$root_condition_id = post('conditions_root_id', array());
				if (in_array($condition->id, $root_condition_id))
					$pricerule_rule_set = $condition;
				else
					$pricerule_rule_set = $this->findConditionObj($root_condition_id[post('host_rule_set')]);

				$this->conditionsRenderPartial('conditions_container', array('pricerule_rule_set'=>$pricerule_rule_set));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function conditionsSaveAll()
		{
			$cache = post('condition_data', array());
			$session_key = $this->_controller->formGetEditSessionKey();
			foreach ($cache as $id=>$data)
			{
				$condition = $this->findConditionObj($id);
				$condition->xml_data = $data;
				$condition->define_form_fields();

				$condition->save(null, $session_key.'_'.$condition->id);
			}
		}
		
		public function onLoadConditionChildSelector()
		{
			try
			{
				$condition = $this->findConditionObj();

				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['conditions_root_id'] = post('conditions_root_id');
				$this->viewData['condition'] = $condition;
				
				$parents = array($condition->id);
				$parents_array = post('condition_parent_id', array());
				$current_id = $condition->id;
				while (array_key_exists($current_id, $parents_array) && $parents_array[$current_id])
					$parents[] = $current_id = $parents_array[$current_id];

				if ($this->controllerMethodExists('conditions_get_new_rule_type'))
					$rule_type = $this->_controller->conditions_get_new_rule_type($condition);
				else
					$rule_type = $this->_controller->conditions_rule_type;

				$this->viewData['options'] = $condition->get_child_options($rule_type, $parents);
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('create_child_form');
		}
		
		protected function renderConditions($current_condition = null)
		{
			$root_condition_id = post('conditions_root_id');
			
			if ($current_condition && in_array($current_condition->id, $root_condition_id))
				$pricerule_rule_set = $current_condition;
			else
				$pricerule_rule_set = $this->findConditionObj($root_condition_id[post('host_rule_set')]);

			$this->conditionsRenderPartial('conditions_container', array('pricerule_rule_set'=>$pricerule_rule_set));
		}
		
		public function onConditionFormEvent()
		{
			try
			{
				$condition = $this->findConditionObj();
				$condition->define_form_fields();
				$this->conditionsRregisterViewPaths($condition);

				$condition->get_condition_obj()->process_config_form_event($condition, $this->_controller);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onCreateCondition()
		{
			try
			{
				$class_name = post('condition_class');
				$subcondition = null;

				$parts = explode(':', $class_name);
				if (count($parts) > 1)
				{
					$subcondition = $parts[1];
					$class_name = $parts[0];
				}

				$parent_condition = $this->findConditionObj();
				$new_condition = new Shop_PriceRuleCondition();
				$new_condition->class_name = $class_name;
				$new_condition->host_rule_set = $parent_condition->host_rule_set;

				$new_condition->define_form_fields();
				if ($subcondition)
					$new_condition->subcondition = $subcondition;

				$new_condition->save();

				$parent_condition->children->add($new_condition, post('edit_session_key').'_'.$parent_condition->id);
				$this->viewData['new_condition_id'] = $new_condition->id;
				$this->renderConditions($parent_condition);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function onDeleteCondition()
		{
			$parent_condition = null;

			try
			{
				$condition = $this->findConditionObj();

				$parent_ids = post('condition_parent_id', array());
				if (isset($parent_ids[$condition->id]))
				{

					$parent_condition = $this->findConditionObj($parent_ids[$condition->id]);
					if ($parent_condition)
					{
						$parent_condition->children->delete($condition, post('edit_session_key').'_'.$parent_condition->id);
					}
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
			
			$this->renderConditions($parent_condition);
		}
		
		public function onCancelConditionSettings()
		{
			$condition = null;

			try
			{
				$new_condition_id = post('new_condition_id');
				if ($new_condition_id)
				{
					$condition = $this->findConditionObj();
					if ($condition)
						$condition->delete();
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}

			$this->renderConditions($condition);
		}
		
		public function conditionsGetCollapseStatus($condition)
		{
			$statuses = Db_UserParameters::get('shop_price_conditions_status', null, array());
			if (array_key_exists($condition->id, $statuses))
				return $statuses[$condition->id];

			return false;
		}
		
		public function onSetCollapseStatus()
		{
			$statuses = Db_UserParameters::get('shop_price_conditions_status', null, array());
			$statuses[post('condition_id')] = post('new_status');
			Db_UserParameters::set('shop_price_conditions_status', $statuses);
		}
		
		public function conditionsPrepareMultiselectData($model, $options)
		{
			try
			{
				$condition = $this->findConditionObj();
				$model = $condition->get_condition_obj()->prepare_filter_model($condition, $model, $options);
			}
			catch (Exception $ex){}
			
			return $model;
		}
	}

?>