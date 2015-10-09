<?

	class Shop_Catalog_Rules extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Shop_RuleConditionsBehavior, Shop_RuleListBehavior';
		public $list_model_class = 'Shop_CatalogPriceRule';
		public $list_record_url = null;

		public $list_options = array();
		public $list_name = null;
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		public $list_custom_prepare_func = null;
		public $list_no_setup_link = false;
		public $list_items_per_page = 20;
		public $list_search_enabled = false;
		public $list_search_fields = array();
		public $list_search_prompt = null;

		public $form_create_title = 'New Rule';
		public $form_edit_title = 'Edit Rule';
		public $form_model_class = 'Shop_CatalogPriceRule';
		public $form_not_found_message = 'Rule not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Rule has been successfully saved';
		public $form_create_save_flash = 'Rule has been successfully added';
		public $form_edit_delete_flash = 'Rule has been successfully deleted';

		protected $required_permissions = array('shop:manage_discounts');
		
		public function __construct()
		{
			$this->globalHandlers[] = 'onUpdateAction';

			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'catalog_rules';
			$this->app_module_name = 'Shop';
			$this->form_redirect = url('/shop/catalog_rules');
		}
		
		public function index()
		{
			$this->app_page_title = 'Catalog Price Rules';
		}
		
		protected function index_onShowApplyForm()
		{
			$this->renderPartial('apply_rules_form');
		}
		
		protected function index_onApplyRules()
		{
			try
			{
				$processed_products = Shop_CatalogPriceRule::apply_price_rules();
				Phpr::$response->redirect(url('shop/catalog_rules/success/'.$processed_products));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function success($count)
		{
			Phpr::$session->flash['success'] = 'Price rules have been successfully applied to '.$count.' product(s).';
			Phpr::$response->redirect(url('shop/catalog_rules'));
		}
		
		public function create_formBeforeRender($model)
		{
			$model->init_conditions($this->formGetEditSessionKey());
		}
		
		protected function process_save()
		{
			$this->conditionsSaveAll();
			Phpr::$response->redirect(url('shop/catalog_rules'));
		}
		
		public function formAfterCreateSave($model, $session_key)
		{
			$this->process_save();
		}

		public function formAfterEditSave($model, $session_key)
		{
			$this->process_save();
		}
		
		protected function onUpdateAction($rule_id)
		{
			try
			{
				if (strlen($rule_id))
				{
					$rule_obj = Shop_CatalogPriceRule::create()->find($rule_id);
					if (!$rule_obj)
						throw new Phpr_ApplicationException('Rule not found');
				} else {
					$rule_obj = new Shop_CatalogPriceRule();
				}
				
				$params = post('Shop_CatalogPriceRule', array());
				$rule_obj->action_class_name = $params['action_class_name'];
				$rule_obj->define_form_fields();
				$rule_obj->set_data($params);

				echo ">>tab_3<<";
				$this->formRenderFormTab($rule_obj, 2);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function set_action_class($model)
		{
			$data = post('Shop_CatalogPriceRule', array());
			$action_class_name = array_key_exists('action_class_name', $data) ? $data['action_class_name'] : $model->action_class_name;

			$model->action_class_name = $action_class_name;
		}
		
		public function formCreateModelObject()
		{
			$modelClass = $this->form_model_class;

			$obj = new $modelClass();
			$this->set_action_class($obj);

			$obj->init_columns_info();
			$obj->define_form_fields($this->formGetContext());

			return $obj;
		}

		public function formFindModelObject($recordId)
		{
			$modelClass = $this->form_model_class;
				
			if (!strlen($recordId))
				throw new Phpr_ApplicationException($this->form_not_found_message);

			$model = new $modelClass();
			$obj = $model->find($recordId);
			
			if (!$obj || !$obj->count())
				throw new Phpr_ApplicationException($this->form_not_found_message);
				
			$this->set_action_class($obj);
			$obj->define_form_fields($this->formGetContext());

			return $obj;
		}
	}

?>