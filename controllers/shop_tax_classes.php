<?

	class Shop_Tax_Classes extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_TaxClass';
		public $list_record_url = null;

		public $form_preview_title = 'Tax Class';
		public $form_create_title = 'New Tax Class';
		public $form_edit_title = 'Edit Tax Class';
		public $form_model_class = 'Shop_TaxClass';
		public $form_not_found_message = 'Tax class not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Tax class has been successfully saved';
		public $form_create_save_flash = 'Tax class has been successfully added';
		public $form_edit_delete_flash = 'Tax class has been successfully deleted';
		
		public $form_grid_csv_export_url = null;

		protected $required_permissions = array('shop:manage_shop_settings');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'taxes';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/tax_classes/edit/');
			$this->form_redirect = url('/shop/tax_classes/');
			$this->form_grid_csv_export_url = url('/shop/tax_classes/');
		}
		
		public function index()
		{
			$this->app_page_title = 'Tax Classes';
		}
	}

?>