<?

	class Shop_Payment extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Cms_PageSelector';
		public $list_model_class = 'Shop_PaymentMethod';
		public $list_record_url = null;
		public $list_reuse_model = false;

		public $form_preview_title = 'Payment Method';
		public $form_create_title = 'New Payment Method';
		public $form_edit_title = 'Edit Payment Method';
		public $form_model_class = 'Shop_PaymentMethod';
		public $form_not_found_message = 'Payment method not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'The payment method has been successfully saved';
		public $form_create_save_flash = 'The payment method has been successfully added';
		public $form_edit_delete_flash = 'The payment method has been successfully deleted';

		protected $required_permissions = array('shop:manage_shop_settings');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'payment';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/payment/edit/');
			$this->form_redirect = url('/shop/payment');
		}
		
		public function index()
		{
			$this->app_page_title = 'Payment Methods';
			Shop_PaymentMethod::create_partials();
		}
		
		public function formCreateModelObject()
		{
			$obj = Shop_PaymentMethod::create();

			$class_name = Phpr::$router->param('param1');
			if (!Phpr::$classLoader->load($class_name))
				throw new Phpr_ApplicationException("Class {$class_name} not found.");

			$obj->class_name = $class_name;
			$obj->init_columns_info();
			$obj->define_form_fields();

			return $obj;
		}
		
		protected function index_onLoadAddPopup()
		{
			try
			{
				$payment_types = Core_ModuleManager::findById('shop')->listPaymentTypes();

				$type_list = array();
				foreach ($payment_types as $class_name)
				{
					$obj = new $class_name();
					$info = $obj->get_info();
					if (array_key_exists('name', $info))
					{
						$info['class_name'] = $class_name;
						$type_list[] = $info;
					}
				}
				
				usort( $type_list, array('Shop_Payment', 'payment_method_cmp') );

				$this->viewData['type_list'] = $type_list;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('add_method_form');
		}
		
		public static function payment_method_cmp( $a, $b )
		{
			return strcasecmp( $a['name'], $b['name'] );
		}
		
		public function listGetRowClass($model)
		{
			return $model->enabled ? null : 'disabled';
		}
	}

?>