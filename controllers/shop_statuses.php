<?

	class Shop_Statuses extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_OrderStatus';
		public $list_record_url = null;

		public $form_create_title = 'New Status';
		public $form_edit_title = 'Edit Status';
		public $form_model_class = 'Shop_OrderStatus';
		public $form_not_found_message = 'Status not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Status has been successfully saved';
		public $form_create_save_flash = 'Status has been successfully added';
		public $form_edit_delete_flash = 'Status has been successfully deleted';

		protected $globalHandlers = array(
			'onLoadTransitionForm', 
			'onAddTransition', 
			'onUpdateTransitionList', 
			'onDeleteTransition');

		protected $access_for_groups = array(Users_Groups::admin);

		public function __construct()
		{
			parent::__construct();
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/statuses/edit/');
			$this->form_redirect = url('/shop/statuses');
		}
		
		public function index()
		{
			$this->app_page_title = 'Order Statuses and Transitions';
		}
		
		protected function onLoadTransitionForm($stateId=null)
		{
			try
			{
				$transition = new Shop_StatusTransition();
				$transition->stateId = $stateId;
				$transition->define_form_fields();

				$this->viewData['transition'] = $transition;
				$this->viewData['session_key'] = post('sessionKey');
				$this->renderPartial('transition_form');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onAddTransition($stateId=null)
		{
			try
			{
				$transition = Shop_StatusTransition::create();
				$transition->init_columns_info();
				
				$status = $this->getStatusObj($stateId);
				$transitions = $status->list_related_records_deferred('outcoming_transitions', post('state_session_key'));
				$transitionData = $_POST['Shop_StatusTransition'];
				foreach ($transitions as $existingTransition)
				{
					if ($existingTransition->to_state_id == $transitionData['to_state_id'] 
						&& $existingTransition->role_id == $transitionData['role_id'])
						throw new Phpr_ApplicationException('Transition with selected parameters already exists.');
				}
				
				$transition->save($transitionData);
				$status->outcoming_transitions->add($transition, post('state_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateTransitionList($stateId=null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getStatusObj($stateId);
				$this->renderPartial('transition_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteTransition($stateId=null)
		{
			try
			{
				$status = $this->getStatusObj($stateId);

				$transition = Shop_StatusTransition::create()->find(post('transitionId'));
				if ($transition)
					$status->outcoming_transitions->delete($transition, $this->formGetEditSessionKey());
					
				$this->viewData['form_model'] = $status;
				$this->renderPartial('transition_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
  
		private function getStatusObj($id)
		{
			$state = Shop_OrderStatus::create();
			return strlen($id) ? $state->find($id) : $state;
		}
	}

?>