<?

	class Shop_Manufacturers extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_Manufacturer';
		public $list_record_url = null;

		public $form_preview_title = 'Manufacturer';
		public $form_create_title = 'New Manufacturer';
		public $form_edit_title = 'Edit Manufacturer';
		public $form_model_class = 'Shop_Manufacturer';
		public $form_not_found_message = 'Manufacturer not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'Manufacturer has been successfully saved';
		public $form_create_save_flash = 'Manufacturer has been successfully added';
		public $form_edit_delete_flash = 'Manufacturer has been successfully deleted';
		
		public $list_search_enabled = true;
		public $list_search_fields = array('@name');
		public $list_search_prompt = 'find manufacturers by name';

		protected $required_permissions = array('shop:manage_products');
		public $globalHandlers = array('onUpdateStatesList');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/manufacturers/edit/');
			$this->form_redirect = url('/shop/manufacturers/');
		}
		
		public function index()
		{
			$this->app_page_title = 'Product Manufacturers';
		}
		
		protected function index_onDeleteSelected()
		{
			$deleted = 0;

			$ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $ids;

			foreach ($ids as $id)
			{
				$manufactuer = null;
				try
				{
					$manufactuer = Shop_Manufacturer::create()->find($id);
					if (!$manufactuer)
						throw new Phpr_ApplicationException('Manufacturer with identifier '.$id.' not found.');

					$manufactuer->delete();
					$deleted++;
				}
				catch (Exception $ex)
				{
					if (!$manufactuer)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting manufacturer "'.$manufactuer->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($deleted)
				Phpr::$session->flash['success'] = 'Manufacturers deleted: '.$deleted;

			$this->renderPartial('manufacturers_page_content');
		}
		
		public function listGetRowClass($model)
		{
			if ($model instanceof Shop_Manufacturer)
				return $model->is_disabled ? 'disabled' : null;
		}
		
		public function create_formBeforeRender($model)
		{
			$model->set_default_country();
		}
		
		protected function onUpdateStatesList()
		{
			$data = post('Shop_Manufacturer');

			$form_model = $this->formCreateModelObject();
			$form_model->country_id = $data['country_id'];
			echo ">>form_field_container_state_idShop_Manufacturer<<";
			$this->formRenderFieldContainer($form_model, 'state');
		}
		
		public function formAfterSave($model, $session_key)
		{
			Db_UserParameters::set('manufacturer_def_country', $model->country_id);
			Db_UserParameters::set('manufacturer_def_state', $model->state_id);
		}
	}

?>