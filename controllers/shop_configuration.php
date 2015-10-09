<?

	class Shop_Configuration extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'eCommerce Settings';
		public $form_model_class = 'Shop_ConfigurationRecord';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'eCommerce configuration has been saved.';

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
				$record = Shop_ConfigurationRecord::get();
				if (!$record)
					throw new Phpr_ApplicationException('eCommerce configuration is not found.');
				
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
			$record = Shop_ConfigurationRecord::get();
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_ConfigurationRecord::get();
			$this->edit_onCancel($record->id);
		}
		
		protected function index_onUpdateTaxInclStates()
		{
			try
			{
				$record = Shop_ConfigurationRecord::get();
				$record->init_columns_info();
				$record->define_form_fields();
				$record->set_data(post('Shop_ConfigurationRecord', array()));
				echo ">>form_field_container_tax_inclusive_state_idShop_ConfigurationRecord<<";
				$this->formRenderFieldContainer($record, 'tax_inclusive_state_id');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>