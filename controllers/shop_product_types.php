<?

	class Shop_Product_Types extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_ProductType';
		public $list_record_url = null;

		public $form_preview_title = 'Product Type';
		public $form_create_title = 'New Product Type';
		public $form_edit_title = 'Edit Product Type';
		public $form_model_class = 'Shop_ProductType';
		public $form_not_found_message = 'Product type not found.';
		public $form_redirect = null;

		public $form_edit_save_flash = 'The product type has been successfully saved.';
		public $form_create_save_flash = 'The product type has been successfully added.';
		public $form_edit_delete_flash = 'The product type has been successfully deleted.';

		public $list_items_per_page = 20;

		protected $required_permissions = array('shop:manage_products');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/product_types/edit/');
			$this->form_redirect = url('/shop/product_types');
		}

		public function index()
		{
			$this->app_page_title = 'Product Types';
		}

	}

?>