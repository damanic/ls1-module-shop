<?

	class Shop_Customer_Groups extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_CustomerGroup';
		public $list_record_url = null;

		public $form_preview_title = 'Customer Group';
		public $form_create_title = 'New Customer Group';
		public $form_edit_title = 'Edit Customer Group';
		public $form_model_class = 'Shop_CustomerGroup';
		public $form_not_found_message = 'Customer group not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Customer group has been successfully saved';
		public $form_create_save_flash = 'Customer group has been successfully added';
		public $form_edit_delete_flash = 'Customer group has been successfully deleted';

		protected $required_permissions = array('shop:manage_orders_and_customers');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'customers';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/customer_groups/edit/');
			$this->form_redirect = url('/shop/customer_groups/');
		}
		
		public function index()
		{
			$this->app_page_title = 'Customer Groups';
		}
	}

?>