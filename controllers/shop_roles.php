<?

	class Shop_Roles extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_Role';
		public $list_record_url = null;

		public $form_create_title = 'New Role';
		public $form_edit_title = 'Edit Role';
		public $form_model_class = 'Shop_Role';
		public $form_not_found_message = 'Role not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Role has been successfully saved';
		public $form_create_save_flash = 'Role has been successfully added';
		public $form_edit_delete_flash = 'Role has been successfully deleted';
		
		protected $access_for_groups = array(Users_Groups::admin);

		public function __construct()
		{
			parent::__construct();
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/roles/edit/');
			$this->form_redirect = url('/shop/roles');
		}
		
		public function index()
		{
			$this->app_page_title = 'User Roles';
		}
	}

?>