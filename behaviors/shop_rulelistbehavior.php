<?php

	class Shop_RuleListBehavior extends Phpr_ControllerBehavior
	{
		public $rules_model_class = 'Shop_CatalogPriceRule';
		public $rules_edit_url = 'shop/catalog_rules/edit';
		
		public function __construct($controller)
		{
			parent::__construct($controller);
			
			$this->_controller->addCss('/modules/shop/behaviors/shop_ruleconditionsbehavior/resources/css/conditions.css?'.module_build('shop'));
			$this->_controller->addJavaScript('/modules/shop/behaviors/shop_rulelistbehavior/resources/javascript/rules.js?'.module_build('shop'));
			$this->hideAction('rulesRender');
			$this->hideAction('rulesRenderPartial');
			$this->hideAction('rulesGetCollapseStatus');

			$this->addEventHandler('onSetRuleOrders');
			$this->addEventHandler('onSetRuleCollapseStatus');
			$this->addEventHandler('onDeleteRule');
		}

		public function rulesRender()
		{
			$rules = new $this->_controller->rules_model_class();
			$this->viewData['rules'] = $rules->order('sort_order')->find_all();
			$this->renderPartial('rules_container');
		}
		
		public function rulesRenderPartial($view, $params = array())
		{
			$this->renderPartial($view, $params);
		}
		
		public function rulesGetCollapseStatus($rule)
		{
			$statuses = Db_UserParameters::get('shop_price_rules_status', null, array());
			if (array_key_exists($rule->id, $statuses))
				return $statuses[$rule->id];

			return false;
		}
		
		public function onSetRuleCollapseStatus()
		{
			$statuses = Db_UserParameters::get('shop_price_rules_status', null, array());
			$statuses[post('rule_id')] = post('new_status');
			Db_UserParameters::set('shop_price_rules_status', $statuses);
		}
		
		public function onSetRuleOrders()
		{
			try
			{
				$item_ids = explode(',', post('item_ids'));
				$sort_orders = explode(',', post('sort_orders'));

				$obj = new $this->_controller->rules_model_class();
				$obj->set_orders($item_ids, $sort_orders);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onDeleteRule()
		{
			try
			{
				$rule_id = post('rule_id');

				if (strlen($rule_id))
				{
					$obj = new $this->_controller->rules_model_class();
					$obj = $obj->find($rule_id);

					if ($obj)
					{
						$obj->delete();
						Phpr::$session->flash['success'] = 'The rule has been successfully deleted.';
					}
				}

				$rules = new $this->_controller->rules_model_class();
				$this->viewData['rules'] = $rules->order('sort_order')->find_all();
				$this->renderPartial('rules_container');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}
	
?>