<?

	class Shop_Currency_Converter_Settings extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Currency Converter';
		public $form_model_class = 'Shop_CurrencyConversionParams';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Currency converter parameters have been saved.';

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
				$record = Shop_CurrencyConversionParams::get();
				if (!$record)
					throw new Phpr_ApplicationException('Currency converter parameters not found.');
				
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
			$model = Shop_CurrencyConversionParams::get();
				
			$params = post('Shop_CurrencyConversionParams', array());
			if (isset($params['class_name']))
				$model->class_name = $params['class_name'];
			
			$model->define_form_fields();

			return $model;
		}

		protected function index_onUpdateConverterParams()
		{
			$record = Shop_CurrencyConversionParams::get();
			$params = post('Shop_CurrencyConversionParams', array());
			$record->class_name = $params['class_name'];
			$record->define_form_fields();
			
			echo ">>tab_2<<";
			$this->formRenderFormTab($record, 1);
		}

		protected function index_onSave()
		{
			$record = Shop_CurrencyConversionParams::get();
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_CurrencyConversionParams::get();
			$this->edit_onCancel($record->id);
		}
	}

?>