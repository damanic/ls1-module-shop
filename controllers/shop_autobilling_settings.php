<?

	class Shop_AutoBilling_Settings extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Automated Billing Settings';
		public $form_model_class = 'Shop_AutoBillingParams';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Automated billing settings have been saved.';

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			
			$this->form_redirect = url('system/settings/');
		}

		public function index()
		{
			try
			{
				$record = Shop_AutoBillingParams::get();
				if (!$record)
					throw new Phpr_ApplicationException('Automated bulling parameters not found in the database.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		public function formFindModelObject($recordId)
		{
			$model = Shop_AutoBillingParams::get();
			$model->define_form_fields();

			return $model;
		}

		protected function index_onSave()
		{
			$record = Shop_AutoBillingParams::get();
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_AutoBillingParams::get();
			$this->edit_onCancel($record->id);
		}
	}

?>