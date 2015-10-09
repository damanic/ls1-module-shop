<?

	class Shop_Extras_Sets extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_ExtraOptionSet';
		public $list_record_url = null;

		public $form_preview_title = 'Extra Option Set';
		public $form_create_title = 'New Extra Option Set';
		public $form_edit_title = 'Edit Extra Option Set';
		public $form_model_class = 'Shop_ExtraOptionSet';
		public $form_not_found_message = 'Option set not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'The option set has been successfully saved';
		public $form_create_save_flash = 'The option set has been successfully added';
		public $form_edit_delete_flash = 'The option set has been successfully deleted';

		protected $required_permissions = array('shop:manage_shop_settings');
		
		protected $globalHandlers = array(
			'onLoadExtraOptionForm',
			'onSaveExtraOption',
			'onUpdateExtraOptionList',
			'onDeleteExtraOption',
			'onSetExtraOrders',
			'onLoadExtraOptionGroupForm',
			'onSetExtraGroupName'
		);

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/extras_sets/edit/');
			$this->form_redirect = url('/shop/extras_sets');
		}
		
		public function index()
		{
			$this->app_page_title = 'Extra Option Sets';
		}
		
		protected function onLoadExtraOptionForm()
		{
			try
			{
				$this->resetFormEditSessionKey();

				$id = post('extra_option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if (!$option)
					throw new Phpr_ApplicationException('Option not found');

				$option->define_form_fields();

				$this->viewData['option'] = $option;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['option_id'] = post('extra_option_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('extra_option_form');
		}
		
		protected function onSaveExtraOption($set_id)
		{
			try
			{
				$id = post('option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if (!$option)
					throw new Phpr_ApplicationException('Option not found');
					
				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $option);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $option);

				$set = $this->getSetObj($set_id);

				$option->init_columns_info();
				$option->define_form_fields();
				$option->option_in_set = 1;
				$option->save(post('Shop_ExtraOption'), $this->formGetEditSessionKey());
				
				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $option);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $option);

				if (!$id)
					$set->extra_options->add($option, post('set_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		private function getSetObj($id)
		{
			return strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
		}
		
		protected function onUpdateExtraOptionList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getSetObj($parentId);
				$this->renderPartial('extra_option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteExtraOption($parentId = null)
		{
			try
			{
				$set = $this->getSetObj($parentId);

				$id = post('extra_option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if ($option)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $option);
					$set->extra_options->delete($option, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $set;
				$this->renderPartial('extra_option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onSetExtraOrders($parent_id)
		{
			try
			{
				Shop_ExtraOption::set_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadExtraOptionGroupForm()
		{
			$this->renderPartial('extra_group_name');
		}
		
		protected function onSetExtraGroupName()
		{
			try
			{
				$group_name = trim(post('group_name'));
				if (!strlen($group_name))
					throw new Phpr_ApplicationException('Please specify the group name');
				
				$form_model = Shop_ExtraOption::create();
				$form_model->define_form_fields();
				$form_model->group_name = $group_name;
				
				$this->preparePartialRender('form_field_container_group_nameShop_ExtraOption');
				$this->formRenderFieldContainer($form_model, 'group_name');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}

		}
	}

?>