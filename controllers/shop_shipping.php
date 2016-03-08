<?

	class Shop_Shipping extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Db_FilterBehavior';

		public $list_model_class = 'Shop_ShippingOption';
		public $list_record_url = null;
		public $list_reuse_model = false;

		public $form_preview_title = 'Shipping Option';
		public $form_create_title = 'New Shipping Option';
		public $form_edit_title = 'Edit Shipping Option';
		public $form_model_class = 'Shop_ShippingOption';
		public $form_not_found_message = 'Shipping option not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'The shipping option has been successfully saved';
		public $form_create_save_flash = 'The shipping option has been successfully added';
		public $form_edit_delete_flash = 'The shipping option has been successfully deleted';

		public $form_grid_csv_export_url = null;

		protected $required_permissions = array('shop:manage_shop_settings');


		public $list_render_filters = false;

		public $filter_onApply = 'listReload();';
		public $filter_onRemove = 'listReload();';
		public $filter_list_title = 'Filter options';

		public $filter_switchers = array(
			'hide_disabled'=>array('name'=>'Hide disabled options', 'class_name'=>'Shop_HideDisabledShippingSwitcher')
		);


		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'shipping';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/shipping/edit/');
			$this->form_redirect = url('/shop/shipping');
			$this->form_grid_csv_export_url = url('/shop/shipping/');
		}
		
		public function index()
		{
			$this->app_page_title = 'Shipping Options';
		}
		
		protected function index_onLoadAddPopup()
		{
			try
			{
				$shipping_types = Core_ModuleManager::findById('shop')->listShippingTypes();

				$type_list = array();
				foreach ($shipping_types as $class_name)
				{
					$obj = new $class_name();
					$info = $obj->get_info();
					if (array_key_exists('name', $info))
					{
						$info['url'] = url('/shop/shipping/create/'.$class_name);
						$type_list[] = $info;
					}
				}

				usort( $type_list, array('Shop_Shipping', 'shipping_option_cmp') );

				$this->viewData['type_list'] = $type_list;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('add_method_form');
		}
		
		public function formCreateModelObject()
		{
			$obj = Shop_ShippingOption::create();

			$class_name = trim(Phpr::$request->getField('widget_model_class'));

			if (!strlen($class_name))
				$class_name = Phpr::$router->param('param1');

			if (!Phpr::$classLoader->load($class_name))
				throw new Phpr_ApplicationException("Class {$class_name} not found.");

			$obj->class_name = $class_name;
			$obj->init_columns_info();
			$obj->define_form_fields();

			return $obj;
		}
		
		public function listGetRowClass($model)
		{
			return $model->enabled ? null : 'disabled';
		}

		public function listPrepareData()
		{
			$obj = new Shop_ShippingOption();
			$this->filterApplyToModel($obj);
			return $obj;
		}

		
		public static function shipping_option_cmp( $a, $b )
		{
			return strcasecmp( $a['name'], $b['name'] );
		}
	}

?>