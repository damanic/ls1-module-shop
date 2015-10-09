<?

	class Shop_Products extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Db_FilterBehavior, Shop_BundleProductUiBehavior, Cms_PageSelector, Backend_FileBrowser, Shop_OptionMatrixBehavior';
		public $list_model_class = 'Shop_Product';
		public $list_record_url = null;
		public $list_options = array();
		public $list_name = null;
		public $list_data_context = null;
		public $list_cell_individual_partial = array(
			'product_rating'=>'rating_cell',
			'product_rating_all'=>'rating_all_cell',
			'image'=>'product_image_cell'
		);
		public $list_handle_row_click = true;

		public $form_preview_title = 'Product';
		public $form_create_title = 'New Product';
		public $form_edit_title = 'Edit Product';
		public $form_model_class = 'Shop_Product';
		public $form_not_found_message = 'Product not found';
		public $form_redirect = null;
		public $form_delete_redirect = null;
		public $form_grid_csv_export_url = null;
		public $form_no_flash = false;
		public $form_create_save_redirect = null;
		public $form_edit_save_auto_timestamp = true;
		
		public $form_unique_prefix = null;
		
		public $form_edit_save_flash = 'The product has been successfully saved';
		public $form_create_save_flash = 'The product has been successfully added';
		public $form_edit_delete_flash = 'The product has been successfully deleted';
		public $form_flash_id = 'form_flash';
		public $enable_concurrency_locking = true;
		
		public $list_search_enabled = true;
		public $list_search_fields = array('shop_products.name', 'shop_products.sku', '(select group_concat(sku) from shop_products sku_list where sku_list.product_id is not null and sku_list.product_id=shop_products.id)', '(select group_concat(sku) from shop_option_matrix_records where product_id=shop_products.id)');
		public $list_search_prompt = 'find products by name or SKU';
		
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		public $list_custom_prepare_func = null;
		public $list_no_setup_link = false;
		public $list_top_partial = null;
		public $list_items_per_page = 20;

		public $list_render_filters = false;
		
		public $filter_onApply = 'listReload();';
		public $filter_onRemove = 'listReload();';
		public $filter_list_title = 'Filter products';
		
		public $csv_import_file_columns_header = 'File Columns';
		public $csv_import_db_columns_header = 'LemonStand Product Columns';
		public $csv_import_data_model_class = 'Shop_Product';
		public $csv_import_config_model_class = 'Shop_ProductCsvImportModel';
		public $csv_import_name = 'Product import';
		public $csv_import_url = null;
		public $csv_import_short_name = 'Products';
		public $include_preview_breadcrumb = false;

		public $filebrowser_dirs = array(
			'resources'=>array('path'=>'/resources', 'root_upload'=>false)
		);
		public $filebrowser_absoluteUrls = true;
		public $filebrowser_onFileClick = null;
		public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';

		protected $refererUrl = null;
		protected $refererName = null;
		protected $refererObj = false;
		
		protected $previewUrl = null;

		public $filter_filters = array(
			'categories'=>array('name'=>'Category', 'class_name'=>'Shop_CategoryFilter', 'prompt'=>'Please choose product categories you want to include to the list. Products from other categories will be excluded.', 'added_list_title'=>'Added Categories'),
			'groups'=>array('name'=>'Group', 'class_name'=>'Shop_CustomGroupFilter', 'cancel_if_all'=>false, 'prompt'=>'Please choose product groups you want to include to the list. Products from other groups will be excluded.', 'added_list_title'=>'Added Groups'),
			'product_types'=>array('name'=>'Product type', 'class_name'=>'Shop_ProductTypeFilter', 'prompt'=>'Please choose product types you want to include to the list. Products of other types will be excluded.', 'added_list_title'=>'Added Types'),
			'manufacturers'=>array('name'=>'Manufacturer', 'class_name'=>'Shop_ManufacturerFilter', 'prompt'=>'Please choose manufacturers you want to include to the list. Products of other manufacturers will be excluded.', 'added_list_title'=>'Added Manufacturers')
		);
		
		public $filter_switchers = array(
			'hide_disabled'=>array('name'=>'Hide disabled products', 'class_name'=>'Shop_HideDisabledProductsSwitcher')
		);

		protected $globalHandlers = array(
			'onLoadGroupedProductForm',
			'onUpdateGroupedProductList',
			'onAddGroupedProduct',
			'onDeleteGroupedProduct',
			'onLoadCustomAttributeForm',
			'onUpdateCustomAttributeList',
			'onAddCustomAttribute', 
			'onDeleteCustomAttribute',
			'onLoadExtraOptionForm',
			'onAddExtraOption',
			'onUpdateExtraOptionList',
			'onDeleteExtraOption',
			'onSetExtraOrders',
			'onLoadLoadExtraOptionsForm',
			'onLoadExtraOptionSet',
			'onLoadAddRelatedForm',
			'onAddRelatedProducts',
			'onUpdateRelatedList',
			'onRemoveRelatedProduct',
			'onLoadPropertyForm',
			'onAddProperty',
			'onUpdatePropertyList',
			'onDeleteProperty',
			'onLoadSavePropertiesForm',
			'onSavePropSet',
			'onLoadLoadPropertiesForm',
			'onLoadPropSet',
			'onDeletePropertySet',
			'onSetAttibuteOrders',
			'onLoadPriceTierForm',
			'onUpdatePropertyValues',
			'onUpdatePropertyValue',
			'onUpdatePriceTier',
			'onUpdatePriceTierList',
			'onDeletePriceTier',
			'onLoadSaveOptionsForm',
			'onSaveOptionSet',
			'onDeleteOptionSet',
			'onLoadLoadOptionsForm',
			'onLoadOptionSet',
			'onSetOptionOrders',
			'onSetGroupedOrders',
			'onUngroupProduct',
			'onShopCopyPropsForm',
			'onCopyPropertiesProducts',
			'onLoadAddManufacturerForm',
			'onUpdateManufacturerStatesList',
			'onAddManufacturer',
			'onCustomEvent',
			'onLoadExtraOptionGroupForm',
			'onSetExtraGroupName',
			'onSave'
		);

		protected $required_permissions = array('shop:manage_products');

		public function __construct()
		{
			$this->filebrowser_dirs['resources']['path'] = '/'.Cms_SettingsManager::get()->resources_dir_path;
			
			if (Phpr::$router->action == 'import_csv' || post('import_csv_flag') || Phpr::$router->action == 'import_csv_get_config')
				$this->implement .= ', Backend_CsvImport';
			
			Backend::$events->fireEvent('shop:onConfigureProductsPage', $this);
			
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';
			$this->list_record_url = url('/shop/products/edit/');
			$this->form_grid_csv_export_url = url('/shop/products/');
			$this->list_data_context = 'product_list';
			
			$this->form_redirect = $this->getRefererUrl();
			$this->form_delete_redirect = url('/shop/products');
			$this->form_create_save_redirect = url('/shop/products/edit/%s/'.uniqid());
			//$this->form_edit_save_redirect = url('/shop/products/edit/%s/'.uniqid());
			
			$this->csv_import_url = url('/shop/products');

			if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create')
				Backend::$events->fireEvent('shop:onDisplayProductForm', $this);

			if (Phpr::$router->action == 'index')
				Backend::$events->fireEvent('shop:onDisplayProductList', $this);
			if(strpos(Phpr::$request->getReferer(), '/products/preview/'))
				$this->include_preview_breadcrumb = true;
				
			if (post('add_related_product_mode'))
			{
				$this->init_add_related_products_list();
			} else
			{
				$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
				$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
				$this->list_top_partial = 'product_selectors';
			}
			
			if (Phpr::$router->action == 'index')
			{
				$this->list_cell_partial = PATH_APP.'/modules/shop/controllers/shop_products/_product_row_controls.htm';
				$this->list_handle_row_click = false;
			}

			if (post('filter_request'))
				$this->list_top_partial = null;
				
			Backend::$events->fireEvent('shop:onConfigureProductsController', $this);
		}

		public function listPrepareData()
		{
			$updated_data = Backend::$events->fireEvent('shop:onPrepareProductListData', $this);
			foreach ($updated_data as $updated)
			{
				if ($updated)
					return $updated;
			}
			
			$obj = new Shop_Product();
			$this->filterApplyToModel($obj, 'product_list');
			return $obj->where('grouped is null');
		}

		public function listGetRowClass($model)
		{
			if ($model instanceof Shop_Product)
			{
				$result = 'product_'.($model->enabled ? 'enabled' : 'disabled').' ';
				return $result.($model->enabled ? null : 'disabled');
			}
		}
		
		protected function index_onResetFilters()
		{
			$this->filterReset();
			$this->listCancelSearch();
			Phpr::$response->redirect(url('shop/products'));
		}
		
		protected function evalProductNum()
		{
			return Shop_Product::create()->where('grouped is null')->requestRowCount();
		}

		public function index()
		{
			$this->app_page_title = 'Products';
		}
		
		public function formBeforeSave($product, $session_key)
		{
			if ($product->is_new_record())
			{
				$sort_order = post('new_grouped_sort_order', -1);
				$product->grouped_sort_order = $sort_order;
			}

			$in_stock_values = post('in_stock_values', array());
			foreach ($in_stock_values as $product_id=>$value)
			{
				$value = trim($value);
				if (!preg_match('/^\-?[0-9]*$/', $value))
					$product->validation->setError('Invalid units in stock value: '.$value, null, true);
			}
		}

		public function formAfterSave($model, $session_key)
		{
			Shop_Product::update_page_reference($model);

			$in_stock_values = post('in_stock_values', array());
			foreach ($in_stock_values as $product_id=>$value)
			{
				if ($model->id == $product_id)
					continue;
				
				$value = trim($value);
				if (preg_match('/^[0-9]+$/', $value))
					Shop_Product::set_product_units_in_stock($product_id, $value);
			}
		}
		
		public function formAfterCreateSave($category, $session_key)
		{
			if (post('create_close'))
			{
				$this->form_create_save_redirect = url('/shop/products').'?'.uniqid();
			}
		}
		
		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function formAfterEditSave($model, $session_key)
		{
			$this->viewData['form_model'] = Shop_Product::create()->find($model->id);

			$this->renderMultiple(array(
				'form_flash'=>flash(),
				'object-summary'=>'@_product_summary'
			));
			
			return true;
		}
		
		public function getPreviewUrl()
		{
			if ($this->previewUrl !== null)
				return $this->previewUrl;
				
			$product_page = Cms_Page::create()->find_by_action_reference('shop:product');
			if (!$product_page)
				return $this->previewUrl = false;
				
			return $this->previewUrl = Phpr::$request->getRootUrl().root_url($product_page->url);
		}

		/*
		 * Product batch operations
		 */
		
		protected function index_onDuplicateSelected()
		{
			$products_processed = 0;
			$product_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $product_ids;
			
			foreach ($product_ids as $product_id)
			{
				$product = null;
				try
				{
					$product = Shop_Product::create()->find($product_id);
					if (!$product)
						throw new Phpr_ApplicationException('Product with identifier '.$product_id.' not found.');

					$product->duplicate_product();
					$products_processed++;
				}
				catch (Exception $ex)
				{
					if (!$product)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error duplicating product "'.$product->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($products_processed)
			{
				if ($products_processed > 1)
					Phpr::$session->flash['success'] = $products_processed.' products have been successfully duplicated.';
				else
					Phpr::$session->flash['success'] = '1 product has been successfully duplicated.';
			}

			$this->renderPartial('products_page_content');
		}
		
		protected function index_onDeleteSelected()
		{
			$products_processed = 0;
			$product_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $product_ids;

			foreach ($product_ids as $product_id)
			{
				$product = null;
				try
				{
					$product = Shop_Product::create()->find($product_id);
					if (!$product)
						throw new Phpr_ApplicationException('Product with identifier '.$product_id.' not found.');

					$product->delete();
					Backend::$events->fireEvent('core:onAfterFormRecordDelete', $this, $product);
					$products_processed++;
				}
				catch (Exception $ex)
				{
					if (!$product)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting product "'.$product->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($products_processed)
			{
				if ($products_processed > 1)
					Phpr::$session->flash['success'] = $products_processed.' products have been successfully deleted.';
				else
					Phpr::$session->flash['success'] = '1 product has been successfully deleted.';
			}

			$this->renderPartial('products_page_content');
		}

		protected function index_onLoadEnableDisableProductsForm()
		{
			try
			{
				$product_ids = post('list_ids', array());
				
				if (!count($product_ids))
					throw new Phpr_ApplicationException('Please select product(s) to enable or disable.');
				
				$this->viewData['product_count'] = count($product_ids);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('enable_disable_form');
		}
		
		protected function index_onApplyEnabledStatus()
		{
			$enabled = post('enabled') ? 1 : 0;
			$disable_completely = post('disable_completely') ? 1 : 0;
			
			$products_processed = 0;
			$product_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $product_ids;

			foreach ($product_ids as $product_id)
			{
				$product = null;
				try
				{
					$product = Shop_Product::create()->find($product_id);
					if (!$product)
						throw new Phpr_ApplicationException('Product with identifier '.$product_id.' not found.');

					$product->enabled = $enabled;
					$product->disable_completely = $disable_completely;
					$product->save();

					$products_processed++;
				}
				catch (Exception $ex)
				{
					if (!$product)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error updating product "'.$product->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($products_processed)
			{
				if ($products_processed > 1)
					Phpr::$session->flash['success'] = $products_processed.' products have been successfully updated.';
				else
					Phpr::$session->flash['success'] = '1 product has been successfully updated.';
			}

			$this->renderPartial('products_page_content');
		}
		
		/*
		 * Grouping products form the product list
		 */
		
		public function index_onLoadGroupProductsForm()
		{
			try
			{
				$product_ids = post('list_ids', array());
				
				if (count($product_ids) < 2)
					throw new Phpr_ApplicationException('Please select at least 2 products to group.');
				
				$model = new Shop_GroupProductsModel();
				$model->init($product_ids);
				$this->viewData['model'] = $model;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('group_products_form');
		}
		
		protected function index_onGroupSelectedProducts()
		{
			try
			{
				$model = new Shop_GroupProductsModel();
				$model->apply(post(get_class($model)), array());

				Phpr::$session->flash['success'] = 'Products have been successfully grouped.';
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}

			$this->renderPartial('products_page_content');
		}

		/*
		 * Grouped products
		 */
		
		public function formCreateModelObject()
		{
			$context = $this->formGetUniquePrefix();
			
			if ($context == 'csv_import')
				return $this->csvImportGetModelObj();
			
			if ($context == 'csv_grid_import')
			{
				$obj = new Shop_Product();
				$obj->define_form_fields();
				return $obj;
			}

			if ($context != 'grouped')
			{
				$obj = Shop_Product::create();
				$obj->init_columns_info();
				$obj->define_form_fields($context);
				$obj->tax_class_id = Shop_TaxClass::get_default_class_id();
			}
			else
				$obj = $this->initGroupedProduct(null);

			return $obj;
		}

		public function formFindModelObject($id, $copy_relations = false)
		{
			if (Phpr::$router->action == 'preview')
				$context = 'preview';
			else
				$context = $this->formGetUniquePrefix();

			if ($context != 'grouped')
			{
				$obj = Shop_Product::create()->find($id);
				if ($obj)
					$obj->define_form_fields($context);
			}
			else
			 	$obj = $this->initGroupedProduct($id, $copy_relations);
			
			if (!$obj)
				throw new Phpr_ApplicationException($this->form_not_found_message);

			return $obj;
		}
		
		protected function initGroupedProduct($parent_id, $copy_relations = false)
		{
			$grouped_id = post('grouped_product_id');
			if ($grouped_id)
			{
				$obj = Shop_Product::create()->find($grouped_id);
				$obj->define_form_fields('grouped');
			}
			else
			{
				$obj = Shop_Product::create();
				$obj->enabled = false;

				/*
				 * Copy regular fields
				 */
				$data = post('Shop_Product', array());
				$timeZone = Phpr::$config->get('TIMEZONE');
				$timeZoneObj = new DateTimeZone( $timeZone );
				foreach ($data as $field=>$value)
				{
					$column_info = $obj->column($field);
					
					if ($column_info && $column_info->type == db_date)
					{
						$column_obj = $obj->find_column_definition($field);
						$obj->$field = Phpr_DateTime::parse(trim($value), $column_obj->getDateFormat(), $timeZoneObj);
					}
					elseif ($column_info && $column_info->type == db_datetime)
					{
						$column_obj = $obj->find_column_definition($field);
						$obj->$field = Phpr_DateTime::parse(trim($value), $column_obj->getDateFormat().' '.$column_obj->getTimeFormat(), $timeZoneObj);
					}
					else
						$obj->$field = $value;
				}

				if (strlen($obj->url_name))
					$obj->url_name .= '_'.uniqid().time();

				$obj->define_form_fields('grouped');
				$obj->sku = null;

				if ($copy_relations)
				{
					$sessionKey = $this->formGetEditSessionKey();
					
					/*
					 * Copy relations
					 */

					$extension = $this->getExtension('Db_FormBehavior');

					$parent_product = strlen($parent_id) ? 
						$extension->formFindModelObject($parent_id) : 
						$extension->formCreateModelObject();

					$parent_product->copy_properties($obj, $sessionKey, post('edit_session_key'));
				}
			}
				
			return $obj;
		}

		protected function onLoadGroupedProductForm($parent_id)
		{
			try
			{
				$this->resetFormEditSessionKey();
				$this->form_unique_prefix = 'grouped';
				$product = $this->formFindModelObject($parent_id, true);
				
				$in_stock_values = post('in_stock_values', array());
				if (array_key_exists($product->id, $in_stock_values))
					$product->in_stock = trim($in_stock_values[$product->id]);

				$this->viewData['product'] = $product;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['grouped_product_id'] = post('grouped_product_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('grouped_product_form');
		}
		
		protected function onSetGroupedOrders($parent_id)
		{
			try
			{
				$new_product_order = Shop_Product::set_orders(post('item_ids'), post('sort_orders'));
				echo $new_product_order;
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onAddGroupedProduct($parentId = null)
		{
			try
			{
				$grouped_id = post('grouped_product_id');

				$grouped_product = Shop_Product::create();
				if ($grouped_id)
				{
					$grouped_product = $grouped_product->find($grouped_id);
					if (!$grouped_product)
						throw new Phpr_ApplicationException('Grouped product not found');
				}

				$product = $this->getProductObj($parentId);
				$grouped_product->validation->focusPrefix = 'grouped'.$grouped_product->validation->focusPrefix;
				$grouped_product->init_columns_info();
				$grouped_product->define_form_fields('grouped');
				
				if ($grouped_id)
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $grouped_product);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $grouped_product);

				$data = $_POST['Shop_Product'];
				$data['grouped'] = 1;

				$grouped_product->save($data, $this->formGetEditSessionKey());
				
				if ($grouped_id)
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $grouped_product);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $grouped_product);
				
				if (!$grouped_id)
					$product->grouped_products_all->add($grouped_product, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateGroupedProductList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				
				$grouped_product_id = post('updated_product_id');
				if ($grouped_product_id)
				{
					$in_stock_values = post('in_stock_values', array());
					$in_stock_values[$grouped_product_id] = post('updated_stock_value');
					$_POST['in_stock_values'] = $in_stock_values;
				}
				
				$copy_properties_data = post('copy_properties_data');
				$copy_properties_values = array();
				if ($copy_properties_data)
				{
					$copy_properties_data = explode('&', $copy_properties_data);
					foreach ($copy_properties_data as $property_value)
					{
						$pair = explode('=', $property_value);
						if (count($pair) == 2)
						{
							$key = $pair[0];
							$value = $pair[1];
							
							if (substr($key, -2) != '[]')
								$copy_properties_values[$key] = $value;
							else
							{
								$key = substr($key, 0, -2);
								if (!array_key_exists($key, $copy_properties_values))
									$copy_properties_values[$key] = array();
									
								$copy_properties_values[$key][] = $value;
							}
						}
					}
				}

				$properties = array_key_exists('properties', $copy_properties_values) ? $copy_properties_values['properties'] : array();

				if (is_array($properties) && in_array('in_stock', $properties))
				{
				    $updated_product_ids = array_key_exists('list_ids', $copy_properties_values) ? $copy_properties_values['list_ids'] : array();
					$in_stock_values = post('in_stock_values', array());
					
					foreach ($updated_product_ids as $product_id)
					{
						if (isset($in_stock_values[$product_id]))
							unset($in_stock_values[$product_id]);
					}

					$_POST['in_stock_values'] = $in_stock_values;
					
				}

				$this->renderPartial('grouped_product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteGroupedProduct($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$this->form_unique_prefix = 'grouped';
				$grouped_product = Shop_Product::create()->find(post('grouped_product_id'));
				if ($grouped_product)
				{
					$grouped_product->init_columns_info();
					$grouped_product->define_form_fields('grouped');
					$grouped_product->delete();
					$product->grouped_products_all->delete($grouped_product, $this->formGetEditSessionKey());
					Backend::$events->fireEvent('core:onAfterFormRecordDelete', $this, $grouped_product);
				}

				$this->viewData['form_model'] = $product;
				$this->renderPartial('grouped_product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUngroupProduct($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$this->form_unique_prefix = 'grouped';
				$data = post('Shop_Product');
				$grouped_product = Shop_Product::create()->find(post('grouped_product_id'));
				if ($grouped_product)
					$grouped_product->ungroup($product, $this->formGetEditSessionKey(), $data['categories']);

				$this->viewData['form_model'] = $product;
				$this->renderPartial('grouped_product_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		private function getProductObj($id)
		{
			return strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
		}
		
		protected function onShopCopyPropsForm($id = null)
		{
			try
			{
				$product = $this->getProductObj($id);

				$this->viewData['form_model'] = $product;
				$this->viewData['edit_session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				
				$this->viewData['properties'] = $product->list_copy_properties();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('copy_properties_form');
		}
		
		protected function onCopyPropertiesProducts($id = null)
		{
			try
			{
				$product = $this->getProductObj($id);
				if (!$product)
					throw new Phpr_ApplicationException('Product not found.');
				
				$properties = post('properties', array());
				$product_ids = post('list_ids', array());

				$product->set_data(post('Shop_Product', array()));

				if (!$product_ids)
					throw new Phpr_ApplicationException('Please select products to copy properties to.');

				if (!$properties)
					throw new Phpr_ApplicationException('Please select properties to copy.');
					
				$product->copy_properties_to_grouped(post('edit_session_key'), $product_ids, $properties, $_POST);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Custom attributes
		 */

		protected function onLoadCustomAttributeForm()
		{
			try
			{
				$id = post('custom_attribute_id');
				$attribute = $id ? Shop_CustomAttribute::create()->find($id) : Shop_CustomAttribute::create();
				if (!$attribute)
					throw new Phpr_ApplicationException('Attribute not found');

				$attribute->define_form_fields();

				$this->viewData['attribute'] = $attribute;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['attribute_id'] = post('custom_attribute_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('custom_attribute_form');
		}
		
		protected function onAddCustomAttribute($parentId = null)
		{
			try
			{
				$id = post('attribute_id');
				$attribute = $id ? Shop_CustomAttribute::create()->find($id) : Shop_CustomAttribute::create();
				if (!$attribute)
					throw new Phpr_ApplicationException('Attribute not found');

				$product = $this->getProductObj($parentId);
				$attribute->init_columns_info();
				$attribute->define_form_fields('grouped');
				$attribute->save(post('Shop_CustomAttribute'), $this->formGetEditSessionKey());

				if (!$id)
					$product->options->add($attribute, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateCustomAttributeList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				$this->renderPartial('option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteCustomAttribute($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$id = post('custom_attribute_id');
				$attribute = $id ? Shop_CustomAttribute::create()->find($id) : Shop_CustomAttribute::create();
				if ($attribute)
				{
					if (!$attribute->can_delete())
						throw new Phpr_ApplicationException('Cannot delete product option because there are orders referring to it.');
					
					$product->options->delete($attribute, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $product;
				$this->renderPartial('option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onLoadSaveOptionsForm()
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				$obj = $this->viewData['obj'] = new Shop_OptionSet();
				$obj->init_columns_info();
				$obj->define_form_fields();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('save_optionset_form');
		}
		
		protected function onSaveOptionSet($parentId = null)
		{
			try
			{
				$obj = new Shop_OptionSet();
				$obj->init_columns_info();
				$obj->define_form_fields();
				
				$data = post('Shop_OptionSet', array());
				
				$name = trim($data['name']);
				if (!strlen($name) && !strlen(trim($data['existing_id'])))
					$obj->validation->setError('Please specify the new option set name or select existing option set.', 'name', true);

				$product = $this->getProductObj($parentId);
				$options = $product->list_related_records_deferred('options', post('product_session_key'));

				if (strlen($name))
					$obj->name = $name;
				else
				{
					$obj = $obj->find($data['existing_id']);
					if (!$obj)
						$obj->validation->setError('Selected option set is not found.', 'existing_id', true);
				}
				
				$obj->copyOptions($options)->save();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteOptionSet()
		{
			try
			{
				$data = post('Shop_OptionSet', array());
				
				$obj = Shop_OptionSet::create()->find($data['existing_id']);
				if ($obj)
					$obj->delete();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadLoadOptionsForm()
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				$obj = $this->viewData['obj'] = new Shop_OptionSet();
				$obj->init_columns_info();
				$obj->define_form_fields('load');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('load_optionset_form');
		}
		
		protected function onLoadOptionSet($parentId = null)
		{
			try
			{
				$data = post('Shop_OptionSet', array());
				if (!strlen(trim($data['existing_id'])))
					throw new Phpr_ApplicationException('Please select an option set.');

				$product = $this->getProductObj($parentId);
				$options = $product->list_related_records_deferred('options', post('product_session_key'));

				$obj = Shop_OptionSet::create()->find($data['existing_id']);
				if (!$obj)
					$obj->validation->setError('Selected option set is not found.', 'existing_id', true);

				foreach ($obj->options as $option)
				{
					foreach ($options as $existing_option)
					{
						if ($existing_option->name == $option->name)
							continue 2;
					}
					
					$new_option = Shop_CustomAttribute::create();
					$new_option->name = $option->name;
					$new_option->attribute_values = $option->attribute_values;
					$new_option->option_key = $option->option_key;

					$new_option->save();

					$product->options->add($new_option, post('product_session_key'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onSetOptionOrders($parentId = null)
		{
			Shop_CustomAttribute::create()->set_item_orders(post('item_ids'), post('sort_orders'));
		}

		/*
		 * Extra options
		 */
		
		protected function onLoadExtraOptionForm()
		{
			try
			{
				$this->resetFormEditSessionKey();

				$id = post('extra_option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if (!$option)
					throw new Phpr_ApplicationException('Option not found');

				$option->define_form_fields();

				$this->viewData['option'] = $option;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['option_id'] = post('extra_option_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('extra_option_form');
		}
		
		protected function onAddExtraOption($parentId = null)
		{
			try
			{
				$id = post('option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if (!$option)
					throw new Phpr_ApplicationException('Option not found');
					
				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $option);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $option);

				$product = $this->getProductObj($parentId);

				$option->init_columns_info();
				$option->define_form_fields();
				$option->save(post('Shop_ExtraOption'), $this->formGetEditSessionKey());
				
				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $option);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $option);

				if (!$id)
					$product->product_extra_options->add($option, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateExtraOptionList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				$this->renderPartial('extra_option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeleteExtraOption($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$id = post('extra_option_id');
				$option = $id ? Shop_ExtraOption::create()->find($id) : Shop_ExtraOption::create();
				if ($option)
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $option);
					$product->product_extra_options->delete($option, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $product;
				$this->renderPartial('extra_option_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onSetExtraOrders($parent_id)
		{
			try
			{
				Shop_ExtraOption::set_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadLoadExtraOptionsForm($parent_id)
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				$obj = $this->viewData['obj'] = new Shop_ExtraOptionSet();
				$obj->init_columns_info();
				$obj->define_form_fields('load');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('load_extraoptionset_form');
		}
		
		protected function onLoadExtraOptionSet($parent_id)
		{
			try
			{
				$data = post('Shop_ExtraOptionSet', array());
				if (!strlen(trim($data['existing_id'])))
					throw new Phpr_ApplicationException('Please select an option set.');

				$product = $this->getProductObj($parent_id);
				$options = $product->list_related_records_deferred('product_extra_options', post('product_session_key'));

				$obj = Shop_ExtraOptionSet::create()->find($data['existing_id']);
				if (!$obj)
					$obj->validation->setError('Selected extra option set not found.', 'existing_id', true);

				foreach ($obj->extra_options as $option)
				{
					foreach ($options as $existing_option)
					{
						if ($existing_option->description == $option->description)
						{
							continue 2;
						}
					}

					$new_option = $option->copy();
					$new_option->save();

					$product->product_extra_options->add($new_option, post('product_session_key'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadExtraOptionGroupForm()
		{
			$this->viewData['form_prefix'] = post('form_prefix');
			$this->renderPartial('extra_group_name');
		}
		
		protected function onSetExtraGroupName()
		{
			try
			{
				$group_name = trim(post('group_name'));
				if (!strlen($group_name))
					throw new Phpr_ApplicationException('Please specify the group name');
				
				$form_model = Shop_ExtraOption::create();
				$form_model->define_form_fields();
				$form_model->group_name = $group_name;
				
				$this->form_unique_prefix = post('form_prefix');
				$this->preparePartialRender(post('form_prefix').'form_field_container_group_nameShop_ExtraOption');
				$this->formRenderFieldContainer($form_model, 'group_name');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}

		}

		/*
		 * Tier price
		 */
		
		protected function onLoadPriceTierForm()
		{
			try
			{
				$id = post('price_tier_id');
				$tier = $id ? Shop_PriceTier::create()->find($id) : Shop_PriceTier::create();
				if (!$tier)
					throw new Phpr_ApplicationException('Price tier not found');

				$tier->define_form_fields();

				$this->viewData['tier'] = $tier;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['tier_id'] = post('price_tier_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('price_tier_form');
		}

		protected function onUpdatePriceTier($parentId = null)
		{
			try
			{
				$id = post('tier_id');
				$tier = $id ? Shop_PriceTier::create()->find($id) : Shop_PriceTier::create();
				if (!$tier)
					throw new Phpr_ApplicationException('Price tier not found');
					
				if (!$id)
					Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $tier);
				else
					Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $tier);

				$product = $this->getProductObj($parentId);

				$tier->init_columns_info();
				$tier->define_form_fields();
				$tier->save(post('Shop_PriceTier'), $this->formGetEditSessionKey());
				
				if (!$id)
					Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $tier);
				else
					Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $tier);

				if (!$id)
					$product->price_tiers->add($tier, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdatePriceTierList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				$this->renderPartial('price_tier_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeletePriceTier($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$id = post('price_tier_id');
				$tier = $id ? Shop_PriceTier::create()->find($id) : Shop_PriceTier::create();
				if ($tier) 
				{
					Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $tier);
					$product->price_tiers->delete($tier, $this->formGetEditSessionKey());
				}

				$this->viewData['form_model'] = $product;
				$this->renderPartial('price_tier_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Related products
		 */
		
		protected function onLoadAddRelatedForm($parentId = null)
		{
			try
			{
				$this->viewData['edit_session_key'] = post('edit_session_key');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('add_related_form');
		}
		
		protected function onAddRelatedProducts($parentId = null)
		{
			try
			{
				$related = post('list_ids', array());
				if (!count($related))
					throw new Phpr_ApplicationException('Please select product(s) to add.');

				$product = $this->getProductObj($parentId);
				$related_products = Shop_Product::create()->where('id in (?)', array($related))->find_all();
				foreach ($related_products as $related_product)
					$product->related_products_all->add($related_product, post('edit_session_key'));
					
				$this->init_add_related_products_list();
				$this->listResetCache();
				$this->preparePartialRender('listadd_related_products_list');
				$this->onListReload();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateRelatedList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				$this->renderPartial('related_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onRemoveRelatedProduct($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$id = post('related_product_id');
				$related_product = Shop_Product::create()->find($id);
				if ($related_product)
					$product->related_products_all->delete($related_product, $this->formGetEditSessionKey());

				$this->viewData['form_model'] = $product;
				$this->renderPartial('related_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function prepare_related_product_list()
		{
			$id = Phpr::$router->param('param1');
			$obj = $this->getProductObj($id);

			$related_products = $obj->list_related_records_deferred('related_products_all', post('edit_session_key', $this->formGetEditSessionKey()));

			$obj = Shop_Product::create();

			$bound = array();
			foreach ($related_products as $related_product)
				$bound[] = $related_product->id;
				
			if (count($bound))
				$obj->where('id not in (?)', array($bound));

			if (strlen($id))
				$obj->where('id <> ?', $id);
			
			return $obj->where('grouped is null');
		}
		
		protected function init_add_related_products_list()
		{
			$this->list_model_class = 'Shop_Product';
			$this->list_columns = array('name', 'sku', 'price');

			$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
			$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
			$this->list_custom_prepare_func = 'prepare_related_product_list';
			$this->list_record_url = null;
			$this->list_search_enabled = true;
			$this->list_no_setup_link = true;
			$this->list_name = 'add_related_products_list';

			$this->list_search_fields = array('shop_products.name', 'shop_products.sku');
			$this->list_search_prompt = 'find products by name or SKU';
			$this->list_items_per_page = 10;
		}

		/*
		 * Properties
		 */
		
		protected function onLoadPropertyForm()
		{
			try
			{
				$id = post('property_id');
				$option = $id ? Shop_ProductProperty::create()->find($id) : Shop_ProductProperty::create();
				if (!$option)
					throw new Phpr_ApplicationException('Attribute not found');
				
				$option->define_form_fields();

				$this->viewData['option'] = $option;
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['property_id'] = post('property_id');
				$this->viewData['trackTab'] = false;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('property_form');
		}
		
		protected function onAddProperty($parentId = null)
		{
			try
			{
				$id = post('property_id');

				$option = $id ? Shop_ProductProperty::create()->find($id) : Shop_ProductProperty::create();
				if (!$option)
					throw new Phpr_ApplicationException('Option not found');

				$product = $this->getProductObj($parentId);
				$option->init_columns_info();
				$option->define_form_fields();
				$option->save(post('Shop_ProductProperty'), $this->formGetEditSessionKey());

				if (!$id)
					$product->properties->add($option, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdatePropertyList($parentId = null)
		{
			try
			{
				$this->viewData['form_model'] = $this->getProductObj($parentId);
				$this->renderPartial('properties_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteProperty($parentId = null)
		{
			try
			{
				$product = $this->getProductObj($parentId);

				$id = post('property_id');
				$option = $id ? Shop_ProductProperty::create()->find($id) : Shop_ProductProperty::create();
				if ($option)
					$product->properties->delete($option, $this->formGetEditSessionKey());

				$this->viewData['form_model'] = $product;
				$this->renderPartial('properties_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onSetAttibuteOrders($parentId = null)
		{
			Shop_ProductProperty::create()->set_item_orders(post('item_ids'), post('sort_orders'));
		}

		protected function onLoadSavePropertiesForm()
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				$obj = $this->viewData['obj'] = new Shop_PropertySet();
				$obj->init_columns_info();
				$obj->define_form_fields();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('save_propset_form');
		}
		
		protected function onSavePropSet($parentId = null)
		{
			try
			{
				$obj = new Shop_PropertySet();
				$obj->init_columns_info();
				$obj->define_form_fields();
				
				$data = post('Shop_PropertySet', array());
				
				$name = trim($data['name']);
				if (!strlen($name) && !strlen(trim($data['existing_id'])))
					$obj->validation->setError('Please specify the new property set name or select existing property set.', 'name', true);

				$product = $this->getProductObj($parentId);
				$properties = $product->list_related_records_deferred('properties', post('product_session_key'));

				if (strlen($name))
					$obj->name = $name;
				else
				{
					$obj = $obj->find($data['existing_id']);
					if (!$obj)
						$obj->validation->setError('Selected property set is not found.', 'existing_id', true);
				}
				
				$obj->copyProperties($properties)->save();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadLoadPropertiesForm()
		{
			try
			{
				$this->viewData['session_key'] = post('edit_session_key');
				$this->viewData['trackTab'] = false;
				$obj = $this->viewData['obj'] = new Shop_PropertySet();
				$obj->init_columns_info();
				$obj->define_form_fields('load');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('load_propset_form');
		}
		
		protected function onLoadPropSet($parentId = null)
		{
			try
			{
				$data = post('Shop_PropertySet', array());
				if (!strlen(trim($data['existing_id'])))
					throw new Phpr_ApplicationException('Please select a property set.');

				$product = $this->getProductObj($parentId);
				$properties = $product->list_related_records_deferred('properties', post('product_session_key'));

				$obj = Shop_PropertySet::create()->find($data['existing_id']);
				if (!$obj)
					$obj->validation->setError('Selected property set is not found.', 'existing_id', true);

				foreach ($obj->properties as $property)
				{
					foreach ($properties as $existing_property)
					{
						if ($existing_property->name == $property->name)
							continue 2;
					}
					
					$option = Shop_ProductProperty::create();
					$option->name = $property->name;

					$option->save();

					$product->properties->add($option, post('product_session_key'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onDeletePropertySet()
		{
			try
			{
				$data = post('Shop_PropertySet', array());
				
				$obj = Shop_PropertySet::create()->find($data['existing_id']);
				if ($obj)
					$obj->delete();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdatePropertyValues()
		{
			$property = Shop_ProductProperty::create();
			$property->define_form_fields();
			$data = post('Shop_ProductProperty', array());
			$property->name = $data['name'];

			$this->formRenderFieldContainer($property, 'value_pickup');
		}
		
		protected function onUpdatePropertyValue()
		{
			$property = Shop_ProductProperty::create();
			$property->define_form_fields();
			$data = post('Shop_ProductProperty', array());
			$property->load_value($data['value_pickup']);

			$this->formRenderFieldContainer($property, 'value');
		}
		
		/*
		 * Manufacturers
		 */
		
		protected function onLoadAddManufacturerForm()
		{
			try
			{
				$this->form_model_class = 'Shop_Manufacturer';
				$this->resetFormEditSessionKey();
				
				$manufacturer = Shop_Manufacturer::create();
				$manufacturer->define_form_fields();
				$manufacturer->set_default_country();
				$this->viewData['manufacturer'] = $manufacturer;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('manufacturer_form');
		}
		
		protected function onUpdateManufacturerStatesList()
		{
			$data = post('Shop_Manufacturer');

			$form_model = Shop_Manufacturer::create();
			$form_model->define_form_fields();
			$form_model->country_id = $data['country_id'];
			echo ">>form_field_container_state_idShop_Manufacturer<<";
			$this->formRenderFieldContainer($form_model, 'state');
		}
		
		protected function onAddManufacturer($id=null)
		{
			try
			{
				$form_model = Shop_Manufacturer::create();

				$form_model->init_columns_info();
				$form_model->define_form_fields();

				$form_model->save(post('Shop_Manufacturer'), $this->formGetEditSessionKey());
				Db_UserParameters::set('manufacturer_def_country', $form_model->country_id);
				Db_UserParameters::set('manufacturer_def_state', $form_model->state_id);

				$product = $this->getProductObj($id);

				$product->manufacturer_id = $form_model->id;
				echo ">>form_field_container_manufacturer_idShop_Product<<";
				$this->formRenderFieldContainer($product, 'manufacturer_link');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Import/export products
		 */
		
		public function import_csv()
		{
			$this->app_page_title = 'Import Products';
		}
		
		// Not sure if these should be removed, just in case other modules depend on this URL?
		// public function export_csv()
		// {
		// 	$iwork_format = post_array_item('export', 'iwork', false);
		// 	$export_format = post_array_item('export', 'format', 'regular');
		// 	$export_images = post_array_item('export', 'images', false);
		// 	
		// 	$this->suppressView();
		// 	try
		// 	{
		// 		if ('regular' == $export_format)
		// 			$output = Shop_ProductExport::export_csv($iwork_format, null, false, $export_images);
		// 		else
		// 			$output = Shop_ProductExportLs2::export_csv($iwork_format, null, false, $export_images);
		// 		
		// 	} catch (Exception $ex)
		// 	{
		// 		$this->app_page_title = 'Export Products';
		// 		$this->_suppressView = false;
		// 		$this->handlePageError($ex);
		// 	}
		// }
		// 
		// public function export_products()
		// {
		// 	$this->app_page_title = 'Export Products CSV';
		// }
		
		/*
		 * Referer support
		 */

		protected function getReferer()
		{
			return Phpr::$router->param('param2');
		}
		
		protected function getRefererName()
		{
			if ($this->refererName != null)
				return $this->refererName;

			$referer = $this->getReferer();
			$refererObj = $this->getRefererObj($referer);
			if ($refererObj && method_exists($refererObj, 'refererName'))
				return $this->refererName = $refererObj->refererName();

			return $this->refererName = 'Product List';
		}

		protected function getRefererUrl()
		{
			if ($this->refererUrl != null)
				return $this->refererUrl;
				
			$referer = $this->getReferer();
			$refererObj = $this->getRefererObj($referer);
			if ($refererObj && method_exists($refererObj, 'refererUrl'))
				return $this->refererUrl = $refererObj->refererUrl();
			
			$referer = post('referer', url('/shop/products'));
			if (strpos($referer, '/create/') !== false)
				$referer = url('/shop/products');
			
			return $this->refererUrl = $referer;
		}

		protected function getRefererTab()
		{
			$referer = $this->getReferer();
			if (strpos($referer, 'report') !== false)
				return 'reports';
				
			return $this->app_page;
		}
		
		protected function getRefererObj($referer)
		{
			if ($this->refererObj !== false)
				return $this->refererObj;

			$referer = strlen($referer) ? $referer : $this->getReferer();
			if (strpos($referer, 'report') !== false)
			{
				$className = $referer;
				if (Phpr::$classLoader->load($className))
					return $this->refererObj = new $className();
			}
			
			return $this->refererObj = null;
		}
		
		/**
		 * Preview page
		 */
		
		public function preview_sales_data($product_id)
		{
			$this->layout = null;
			header("Content-type: text/xml; charset=utf-8");

			$data = Shop_ProductStatisticsData::sales_chart_data($product_id);
			$this->viewData['chart_data'] = $data->chart_data;
			$this->viewData['chart_series'] = $data->chart_series;
		}
		
		public function preview_grouped_chart_data($product_id)
		{
			$this->layout = null;
			header("Content-type: text/xml; charset=utf-8");

			$this->viewData['chart_data'] = Shop_ProductStatisticsData::grouped_chart_data($product_id);
		}
		
		protected function preview_onApproveSelectedReviews($product_id)
		{
			try
			{
				$ids = post('list_ids', array());
				$this->viewData['list_checked_records'] = $ids;

				foreach ($ids as $id)
				{
					$review = Shop_ProductReview::create()->find($id);
					if (!$review)
						throw new Phpr_ApplicationException('Review with identifier '.$id.' not found.');

					$review->approve();
				}

				$this->viewData['form_model'] = $this->formFindModelObject($product_id);
				$this->renderPartial('product_reviews_area');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onDeleteSelectedReviews($product_id)
		{
			try
			{
				$ids = post('list_ids', array());
				$this->viewData['list_checked_records'] = $ids;

				foreach ($ids as $id)
				{
					$review = Shop_ProductReview::create()->find($id);
					if (!$review)
						throw new Phpr_ApplicationException('Review with identifier '.$id.' not found.');

					$review->delete();
				}

				$this->viewData['form_model'] = $this->formFindModelObject($product_id);
				$this->renderPartial('product_reviews_area');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_formBeforeRender($order)
		{
			$this->form_no_flash = true;
		}
		
		protected function preview_onDeleteProduct($product_id)
		{
			try
			{
				$product = Shop_Product::create()->find($product_id);
				if (!$product)
					throw new Phpr_ApplicationException('Product with identifier '.$product_id.' not found.');

				$product->delete();

				Phpr::$session->flash['success'] = $this->form_edit_delete_flash;
				Phpr::$response->redirect(url('shop/products'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/* 
		 * Custom events
		 */
		
		protected function onCustomEvent($id=null)
		{
			$product = null;
			
			if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create')
				$product = $this->getProductObj($id);

			Backend::$events->fireEvent(post('custom_event_handler'), $this, $product);
		}
	}
?>
