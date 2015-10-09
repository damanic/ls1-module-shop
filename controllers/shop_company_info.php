<?

	class Shop_Company_Info extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Company Information and Settings';
		public $form_model_class = 'Shop_CompanyInformation';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Company information has been saved.';

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
				$record = Shop_CompanyInformation::get();
				if (!$record)
					throw new Phpr_ApplicationException('Company information configuration is not found.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function index_onSave()
		{
			$record = Shop_CompanyInformation::get();
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_CompanyInformation::get();
			$this->edit_onCancel($record->id);
		}
	}

?>