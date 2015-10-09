<?

	class Shop_Groups extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_CustomGroup';
		public $list_record_url = null;

		public $form_preview_title = 'Custom Group';
		public $form_create_title = 'New Custom Group';
		public $form_edit_title = 'Edit Custom Group';
		public $form_model_class = 'Shop_CustomGroup';
		public $form_not_found_message = 'Group not found';
		public $form_redirect = null;
		
		public $form_edit_save_flash = 'The custom group has been successfully saved';
		public $form_create_save_flash = 'The custom group has been successfully added';
		public $form_edit_delete_flash = 'The custom group has been successfully deleted';

		public $list_search_fields = array();
		public $list_search_prompt = '';
		public $list_columns = array();
		
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		public $list_custom_prepare_func = null;
		public $list_search_enabled = false;
		public $list_no_setup_link = false;
		public $list_items_per_page = 20;
		
		protected $required_permissions = array('shop:manage_products');

		protected $globalHandlers = array(
			'onLoadAddProductForm',
			'onAddProducts',
			'onUpdateProductList',
			'onRemoveProduct',
			'onRemoveSelectedProducts',
			'onSetOrders',
			'onSortAlphabetically'
		);

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/groups/edit/');
			$this->form_redirect = url('/shop/groups');
			
			if (post('add_product_mode'))
			{
				$this->list_model_class = 'Shop_Product';
				$this->list_columns = array('name', 'sku', 'price');
	
				$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
				$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
				$this->list_custom_prepare_func = 'prepare_product_list';
				$this->list_record_url = null;
				$this->list_search_enabled = true;
				$this->list_no_setup_link = true;
				$this->list_search_fields = array('shop_products.name', 'shop_products.sku');
				$this->list_search_prompt = 'find products by name or SKU';
				$this->list_items_per_page = 10;
			}
		}
		
		public function index()
		{
			$this->app_page_title = 'Custom Groups';
		}

		public function prepare_product_list()
		{
			$id = Phpr::$router->param('param1');
			$obj = $this->getGroupObj($id);

			$product_obj = $obj->get_related_records_deferred_obj('all_products', $this->formGetEditSessionKey());
			$products = Db_DbHelper::objectArray($product_obj->build_sql());

			$obj = new Shop_Product();
			$bound = array();
			foreach ($products as $product)
				$bound[] = $product->id;
				
			if (count($bound))
				$obj->where('id not in (?)', array($bound));
			
			return $obj->where('grouped is null');
		}

		protected function onSetOrders()
		{
		}
		
		protected function onSortAlphabetically($id = null)
		{
			try
			{
				$products = Db_DbHelper::objectArray('select * from shop_products where id in (?)', array(post('product_ids', array())));
				usort($products, array('Shop_Groups', 'cmp_products'));
				
				$product_ids = array();
				foreach ($products as $product)
					$product_ids[] = $product->id;
					
				$sort_orders = post('product_order', array());
				sort($sort_orders);

				$_POST['product_ids'] = $product_ids;
				$_POST['product_order'] = $sort_orders;

				$this->viewData['form_model'] = $this->getGroupObj($id);
				$this->renderPartial('product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public static function cmp_products($a, $b)
		{
			return strcmp($a->name, $b->name);
		}

		protected function onLoadAddProductForm()
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->renderPartial('add_product_form');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function formAfterSave($model, $session_key)
		{
			$model->set_product_orders(post('product_ids', array()), post('product_order', array()));
		}

		protected function onAddProducts($id = null)
		{
			try
			{
				$id_list = post('list_ids', array());
				if (!count($id_list))
					throw new Phpr_ApplicationException('Please select product(s) to add.');

				$group = $this->getGroupObj($id);
				$added_products = $group->list_related_records_deferred('all_products', post('edit_session_key'));
				$added_ids = array();
				foreach ($added_products as $product)
					$added_ids[] = $product->id;

				$products = Shop_Product::create()->where('id in (?)', array($id_list));
				if (count($added_ids))
					$products->where('id not in (?)', array($added_ids));
				
				$products = $products->find_all();

				foreach ($products as $product)
					$group->all_products->add($product, post('edit_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateProductList($id = null)
		{
			$this->viewData['form_model'] = $this->getGroupObj($id);
			$this->renderPartial('product_list');
		}
		
		protected function onRemoveProduct($id = null)
		{
			try
			{
				$group = $this->getGroupObj($id);

				$id = post('product_id');
				$product = Shop_Product::create()->find($id);
				if ($product)
					$group->all_products->delete($product, $this->formGetEditSessionKey());

				$this->viewData['form_model'] = $group;
				$this->renderPartial('product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onRemoveSelectedProducts($id = null)
		{
			try
			{
				$group = $this->getGroupObj($id);

				$id_list = post('list_ids', array());
				if (count($id_list))
				{
					$products = Shop_Product::create()->where('id in (?)', array($id_list))->find_all();

					foreach ($products as $product)
						$group->all_products->delete($product, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $group;
				$this->renderPartial('product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function getProductSortOrder($product_id)
		{
			$product_ids = post('product_ids', array());
			$product_orders = post('product_order', array());
			$group_id = Phpr::$router->param('param1');
			$group_obj = $this->getGroupObj($group_id);
			if (!$group_obj)
				return null;
			
			$group_product_orders = $group_obj->get_products_orders();

			foreach ($product_ids as $index=>$list_product_id)
			{
				if ($list_product_id == $product_id)
				{
					if (array_key_exists($index, $product_orders))
						return $product_orders[$index];

					if (array_key_exists($product_id, $group_product_orders))
						return $group_product_orders[$product_id];

					return $product_id;
				}
			}

			if (array_key_exists($product_id, $group_product_orders) && strlen($group_product_orders[$product_id]))
				return $group_product_orders[$product_id];

			return $product_id;
		}
		
		protected function get_product_list($form_model)
		{
			$product_obj = $form_model->get_related_records_deferred_obj('all_products', $this->formGetEditSessionKey());
			$products = Db_DbHelper::objectArray($product_obj->build_sql());

			$product_list = array();
			foreach ($products as $product)
			{
				$sort_order = $this->getProductSortOrder($product->id);
				$item = array('product'=>$product, 'sort_order'=>$sort_order);
				$product_list[] = (object)$item;
			}
			
			uasort($product_list, array('Shop_Groups', 'sortProducts'));

			return $product_list;
		}
		
		protected static function sortProducts($product_1, $product_2)
		{
			if ($product_1->sort_order == $product_2->sort_order)
				return 0;
				
			if ($product_1->sort_order > $product_2->sort_order)
				return 1;
				
			return -1;
		}

		private function getGroupObj($id)
		{
			return strlen($id) ? Shop_CustomGroup::create()->find($id) : Shop_CustomGroup::create();
		}
	}

?>