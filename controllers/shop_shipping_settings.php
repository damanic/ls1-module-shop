<?

	class Shop_Shipping_Settings extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Shipping Settings';
		public $form_model_class = 'Shop_ShippingParams';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Shipping configuration has been saved.';

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
				$record = Shop_ShippingParams::get(true);
				if (!$record)
					throw new Phpr_ApplicationException('Shipping configuration not found.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function index_onCountryChange()
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_ShippingParams');
			$form_model->country_id = $data['country_id'];
			echo ">>form_field_container_state_idShop_ShippingParams<<";
			$this->formRenderFieldContainer($form_model, 'state');
		}
		
		protected function index_onDefaultLocationCountryChange()
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_ShippingParams');
			$form_model->default_shipping_country_id = $data['default_shipping_country_id'];
			echo ">>form_field_container_default_shipping_state_idShop_ShippingParams<<";
			$this->formRenderFieldContainer($form_model, 'default_state');
		}
		
		protected function index_onOriginToDefault()
		{
			$form_model = $this->formCreateModelObject();
			$data = post('Shop_ShippingParams');
			
			$form_model->default_shipping_country_id = isset($data['country_id']) ? $data['country_id'] : null;
			$form_model->default_shipping_state_id = isset($data['state_id']) ? $data['state_id'] : null;
			$form_model->default_shipping_city = isset($data['city']) ? $data['city'] : null;
			$form_model->default_shipping_zip = isset($data['zip_code']) ? $data['zip_code'] : null;
			
			echo ">>tab_4<<";
			$this->formRenderFormTab($form_model, 3);
		}

		protected function index_onSave()
		{
			$record = Shop_ShippingParams::get(true);
			$this->edit_onSave($record->id);
		}
		
		protected function index_onCancel()
		{
			$record = Shop_ShippingParams::get(true);
			$this->edit_onCancel($record->id);
		}
	}

?>