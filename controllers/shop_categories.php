<?

	class Shop_Categories extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Cms_PageSelector';
		public $list_model_class = 'Shop_Category';
		public $list_no_pagination = false;
		public $list_record_url = null;
		public $list_render_as_tree = false;
		public $list_render_as_sliding_list = true;
		public $list_root_level_label = 'Categories';
		public $list_reuse_model = true;
		public $list_cell_partial = false;
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		public $list_custom_prepare_func = null;
		public $list_no_data_message = 'There are no items in this view';
		public $list_name = null;
		public $list_no_sorting = false;
		public $list_data_context = 'list';
		public $list_no_setup_link = false;
		public $list_handle_row_click = false;
		
		public $list_search_enabled = true;
		public $list_search_fields = array('@name');
		public $list_search_prompt = 'find categories by name';

		public $form_preview_title = 'Category';
		public $form_create_title = 'New Category';
		public $form_edit_title = 'Edit Category';
		public $form_model_class = 'Shop_Category';
		public $form_not_found_message = 'Category not found';
		public $form_redirect = null;
		public $form_create_save_redirect = null;
		public $form_edit_save_auto_timestamp = true;
		
		public $form_edit_save_flash = 'The category has been successfully saved';
		public $form_create_save_flash = 'The category has been successfully added';
		public $form_edit_delete_flash = 'The category has been successfully deleted';
		public $form_flash_id = 'form_flash';
		
		public $list_cell_individual_partial = array(
			'image'=>'product_image_cell'
		);
		
		protected $globalHandlers = array('onSave');
		protected $required_permissions = array('shop:manage_categories');
		
		protected $top_product_order_cache = null;

		protected $previewUrl = null;

		public function __construct()
		{
			Backend::$events->fireEvent('shop:onConfigureCategoriesPage', $this);
			
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'categories';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/categories/edit/');
			$this->form_redirect = url('/shop/categories');
			$this->form_create_save_redirect = url('/shop/categories/edit/%s/'.uniqid());

			if (Phpr::$router->action == 'index')
			{
				$this->list_cell_partial = PATH_APP.'/modules/shop/controllers/shop_categories/_category_row_controls.htm';
				$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
				$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
			}

			if (Phpr::$router->action == 'manage_top_products')
			{
				$this->list_no_pagination = true;
				$this->list_render_as_tree = false;
				$this->list_render_as_sliding_list = false;
				$this->list_model_class = 'Shop_Product';

				$this->list_custom_body_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_top_product_body_cells.htm';
				$this->list_custom_head_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_top_product_head_cells.htm';
				$this->list_custom_prepare_func = 'prepare_top_product_list';
				$this->list_record_url = null;
				$this->list_search_enabled = false;
				$this->list_no_setup_link = false;
				$this->list_no_setup_link = false;
				$this->list_no_data_message = 'There are no top products in this category';
				$this->list_name = 'top_products_list';
				$this->list_no_sorting = true;
			}
			
			if (Phpr::$router->action == 'reorder_categories')
				$this->setup_reorder_categories_list();
		
			if (post('add_top_product_mode') && !post('top_product_post_mode'))
				$this->init_add_top_produsts_list();
			elseif (Phpr::$router->action == 'manage_top_products')
			{
				$this->list_custom_body_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_top_product_body_cells.htm';
				$this->list_custom_head_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_top_product_head_cells.htm';
			}
		}

		protected function init_add_top_produsts_list()
		{
			$this->list_no_pagination = false;
			$this->list_model_class = 'Shop_Product';
			$this->list_columns = array('name', 'sku', 'price');
			$this->list_name = 'add_top_products_list';

			$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
			$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
			$this->list_custom_prepare_func = 'prepare_add_top_products_list';
			$this->list_record_url = null;
			$this->list_search_enabled = true;
			$this->list_no_setup_link = true;
			$this->list_search_fields = array('shop_products.name', 'shop_products.sku');
			$this->list_search_prompt = 'find products by name or SKU';
			$this->list_items_per_page = 10;
			$this->list_no_data_message = 'There are no products in this category or all category products were already added to the top list.';
			$this->list_no_sorting = false;
		}
		
		public function index()
		{
			$this->app_page_title = 'Categories';
		}

		protected function index_onDeleteSelected()
		{
			$items_processed = 0;
			$items_deleted = 0;

			$item_ids = array_reverse(post('list_ids', array()));
			$this->viewData['list_checked_records'] = $item_ids;

			foreach ($item_ids as $item_id)
			{
				$item = null;
				try
				{
					$item = Shop_Category::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Category with identifier '.$item_id.' not found.');
						
					$category_children_id_count = count(Shop_Category::get_children_ids($item_id));
					
					$item->delete();
					$items_deleted += 1 + $category_children_id_count;
					$items_processed += 1 + $category_children_id_count;
					Backend::$events->fireEvent('core:onAfterFormRecordDelete', $this, $item);
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting category "'.$item->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_deleted)
					$message = 'Categories deleted: '.$items_deleted;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('categories_page_content');
		}
		
		public function listPrepareData()
		{
			return Shop_Category::create(true);
		}
		
		public function listGetRowClass($model)
		{
			if ($model instanceof Shop_Category)
			{
				return ($model->category_is_hidden ? 'disabled' : null);
			}
			if ($model instanceof Shop_Product)
			{
				$result = 'product_'.($model->enabled ? 'enabled' : 'disabled').' ';
				return $result.($model->enabled ? null : 'disabled');
			}
		}

		public function getPreviewUrl()
		{
			if ($this->previewUrl !== null)
				return $this->previewUrl;
				
			$product_page = Cms_Page::create()->find_by_action_reference('shop:category');
			if (!$product_page)
				return $this->previewUrl = false;
				
			return $this->previewUrl = Phpr::$request->getRootUrl().root_url($product_page->url);
		}
		
		public function listOverrideSortingColumn($sorting_column)
		{
			if (Phpr::$router->action == 'reorder_categories')
			{
				$result = array('field'=>'front_end_sort_order', 'direction'=>'asc');
				return (object)$result;
			}

			return $sorting_column;
		}
		
		public function manage_top_products($category_id)
		{
			try
			{
				$this->list_render_as_sliding_list = false;
				$this->app_page_title = 'Manage Category Top Products';
				
				$category = Shop_Category::find_category($category_id);
				$this->viewData['category'] = $category;
			}
			catch (exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}
		}
		
		public function prepare_top_product_list($model, $options)
		{
			$model = Shop_Product::create();
			$category_id = Phpr::$router->param('param1');
			if (!preg_match('/^[0-9]+$/', trim($category_id)))
				$category_id = -1;

			$model->where('(shop_products.grouped is null or shop_products.grouped <> 1)');
			$model->where(' exists (select * from shop_products_categories where shop_product_id=shop_products.id and shop_category_id=? and product_category_sort_order is not null)', $category_id);
			$model->order('(select product_category_sort_order from shop_products_categories where shop_product_id=shop_products.id and shop_category_id='.$category_id.' and product_category_sort_order is not null)');
			
			return $model;
		}
		
		protected function get_top_product_sort_order($product_id)
		{
			$category_id = Phpr::$router->param('param1');
			if (!preg_match('/^[0-9]+$/', trim($category_id)))
				$category_id = -1;
			
			if ($this->top_product_order_cache == null)
				$this->top_product_order_cache = Shop_Category::get_top_products_orders($category_id);

			if (array_key_exists($product_id, $this->top_product_order_cache))
				return $this->top_product_order_cache[$product_id];
				
			return $product_id;
		}
		
		protected function manage_top_products_onSetOrders($category_id)
		{
			try
			{
				Shop_Category::set_top_orders($category_id, post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function prepare_add_top_products_list($model, $options)
		{
			$model = Shop_Product::create();
			
			$category_id = Phpr::$router->param('param1');
			if (!preg_match('/^[0-9]+$/', trim($category_id)))
				$category_id = -1;

			$model->where('(shop_products.grouped is null or shop_products.grouped <> 1)');
			$model->join('shop_products_categories', 'shop_products_categories.shop_product_id=shop_products.id');
			$model->where('shop_products_categories.shop_category_id=?', $category_id);
			$model->where('shop_products_categories.product_category_sort_order is null');

			return $model;
		}
		
		protected function manage_top_products_onAddProducts($category_id)
		{
			try
			{
				$category_id = Phpr::$router->param('param1');
				if (preg_match('/^[0-9]+$/', trim($category_id)))
				{
					$id_list = post('list_ids', array());
					if (!count($id_list))
						throw new Phpr_ApplicationException('Please select product(s) to add.');

					$category = Shop_Category::find_category($category_id);

					foreach ($id_list as $product_id)
						$category->add_top_product($product_id);
				}
			
				echo ">>listtop_products_list<<";
				$this->onListReload();
				
				$this->init_add_top_produsts_list();
				$this->listResetCache();
				echo ">>listadd_top_products_list<<";
				$this->onListReload();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function manage_top_products_onRemoveSelected($category_id)
		{
			try
			{
				$category_id = Phpr::$router->param('param1');
				if (preg_match('/^[0-9]+$/', trim($category_id)))
				{
					$id_list = post('list_ids', array());
					if (!count($id_list))
						throw new Phpr_ApplicationException('Please select product(s) to remove.');

					$category = Shop_Category::find_category($category_id);

					foreach ($id_list as $product_id)
						$category->remove_top_product($product_id);
				}
			
				$this->onListReload();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function manage_top_products_onLoadAddProductForm($category_id)
		{
			try
			{
				$this->renderPartial('add_top_product_form');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function reorder_categories()
		{
			$this->app_page_title = 'Manage Category Order';
			$this->setup_reorder_categories_list();
		}
		
		protected function setup_reorder_categories_list()
		{
			$this->list_record_url = null;
			$this->list_no_sorting = true;
			$this->list_no_pagination = true;
			$this->list_no_setup_link = true;
			$this->list_search_enabled = false;
			$this->list_custom_head_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_categories_handle_head_col.htm';
			$this->list_custom_body_cells = PATH_APP.'/modules/shop/controllers/shop_categories/_categories_handle_body_col.htm';
		}
		
		protected function reorder_categories_onSetOrders()
		{
			try
			{
				Shop_Category::set_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function formAfterCreateSave($category, $session_key)
		{
			if (post('create_close'))
			{
				$this->form_create_save_redirect = url('/shop/categories').'?'.uniqid();
			}
		}
		
		public function formAfterEditSave($model, $session_key)
		{
			$this->renderMultiple(array(
				'form_flash'=>flash()
			));
			return true;
		}
	}

?>