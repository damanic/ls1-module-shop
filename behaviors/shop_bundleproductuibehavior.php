<?php

	/**
	 * This behavior provides user interface for managing bundle product items
	 * on the Product Preview page.
	 */
	class Shop_BundleProductUiBehavior extends Phpr_ControllerBehavior
	{
		protected $post_storage = null;
		
		public function __construct($controller)
		{
			parent::__construct($controller);
			
			if (!Phpr::$request->isRemoteEvent())
			{
				$this->_controller->addCss('/modules/shop/behaviors/shop_bundleproductuibehavior/resources/css/bundle.css?'.module_build('shop'));
				$this->_controller->addJavaScript('/modules/shop/behaviors/shop_bundleproductuibehavior/resources/javascript/bundle.js?'.module_build('shop'));
			}

			$this->addEventHandler('on_load_bundle_offer_form');
			$this->addEventHandler('preview_on_save_bundle_offer');
			$this->addEventHandler('preview_on_refresh_bundle_ui');
			$this->addEventHandler('preview_on_delete_bundle_offer');
			$this->addEventHandler('preview_on_set_bundle_offer_order');
			$this->addEventHandler('preview_on_load_add_products_form');
			$this->addEventHandler('preview_on_add_bundle_products');
			$this->addEventHandler('preview_on_set_bundle_offer_item_order');
			$this->addEventHandler('preview_on_remove_bundle_offer_items');
			$this->addEventHandler('preview_on_save_bundle_offer_changes');
			
			Backend::$events->addEvent('shop:onConfigureProductsController', $this, 'configure_products_controller');
		}
		
		public function bundle_render($product)
		{
			$this->renderPartial('bundle_ui_container', array('product'=>$product));
		}
		
		public function bundle_render_partial($view, $params = array())
		{
			$this->renderPartial($view, $params);
		}
		
		public function bundle_get_post_storage()
		{
			if ($this->post_storage)
				return $this->post_storage;
				
			$this->post_storage = new Core_PostStorage('bundle_data');
			
			if (post('bundle_product_data'))
				$this->post_storage->merge('bundle_product_data', post('bundle_product_data'));
			
			return $this->post_storage;
		}
		
		public function bundle_get_item_session_key($item_id)
		{
			$storage = $this->bundle_get_post_storage();
			$keys = $storage->get('item_session_keys', array());

			if (array_key_exists($item_id, $keys))
				return $keys[$item_id];
			
			$keys[$item_id] = uniqid('bundle_item', true);
			$storage->set('item_session_keys', $keys);
			
			return $keys[$item_id];
		}
		
		public function bundle_get_product_field($product_id, $name, $default = null)
		{
			$products_data = $this->bundle_get_post_storage()->get('bundle_product_data', array());
			if (!array_key_exists($product_id, $products_data))
				return $default;

			if (!array_key_exists($name, $products_data[$product_id]))
				return $default;
				
			return trim($products_data[$product_id][$name]);
		}
		
		public function on_load_bundle_offer_form($product_id)
		{
			try
			{
				$id = post('item_id');
				$item = $id ? $this->find_bundle_offer($id) : Shop_ProductBundleOffer::create();
				if (!$item)
					throw new Phpr_ApplicationException('Bundle item not found');
				
				$item->define_form_fields();

				$this->viewData['item'] = $item;
				$this->viewData['product_session_key'] = $this->_controller->formGetEditSessionKey();
				$this->_controller->resetFormEditSessionKey();
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('item_form');
		}
		
		public function preview_on_save_bundle_offer($product_id)
		{
			try
			{
				$id = post('item_id');
				$item = $id ? $this->find_bundle_offer($id) : Shop_ProductBundleOffer::create();
				if (!$item)
					throw new Phpr_ApplicationException('Bundle item not found');
					
				$product = $this->find_product($product_id);

				$item->init_columns_info();
				$item->define_form_fields();
				$item->save(post('Shop_ProductBundleOffer'), post('edit_session_key'));
				
				$_POST['edit_session_key'] = post('product_session_key');

				if (!$id)
					$product->bundle_offers_link->add($item, post('product_session_key'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_on_refresh_bundle_ui($product_id)
		{
			$params = array();
			$product = $params['product'] = $this->find_product($product_id);
			
			$params['current_item_id'] = post('bundle_current_item_id');

			if ($current = post('bundle_navigate_to_item'))
				$params['current_item_id'] = $current;
				
			if (post('bundle_navigate_to_latest'))
			{
				$items = $product->list_related_records_deferred('bundle_offers_link', $this->_controller->formGetEditSessionKey());
				if ($items->count)
					$params['current_item_id'] = $items[$items->count-1]->id;
			}
			
			$this->renderPartial('bundle_ui', $params);
		}
		
		public function preview_on_delete_bundle_offer($product_id)
		{
			try
			{
				$item = $this->find_bundle_offer(post('bundle_current_item_id'));
				$product = $this->find_product($product_id);
				$product->bundle_offers_link->delete($item, $this->_controller->formGetEditSessionKey());

				$this->preview_on_refresh_bundle_ui($product_id);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_on_set_bundle_offer_order()
		{
			try
			{
				Shop_ProductBundleOffer::create()->set_item_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function find_product($id)
		{
			$id = trim($id);
			if (!$id)
				throw new Phpr_ApplicationException('Product not found.');
			
			$product = Shop_Product::create()->where('id=?', $id)->find();
			if (!$product)
				throw new Phpr_ApplicationException('Product not found.');
				
			return $product;
		}
		
		protected function find_bundle_offer($id)
		{
			$id = trim($id);
			if (!$id)
				throw new Phpr_ApplicationException('Bundle item not found.');
				
			$item = Shop_ProductBundleOffer::create()->where('id=?', $id)->find();
			if (!$item)
				throw new Phpr_ApplicationException('Bundle item not found.');
				
			return $item;
		}
		
		public function preview_on_load_add_products_form()
		{
			$this->renderPartial('add_products_form', array(
				'bundle_current_item_id'=>post('bundle_current_item_id'),
				'product_session_key'=>$this->_controller->formGetEditSessionKey()
			));
		}
		
		public function bundle_get_product_popup_list_options()
		{
			$listColumns = array('name', 'sku', 'price');
			
			return array(
				'list_model_class'=>'Shop_Product',
				'list_columns'=>$listColumns,
				'list_custom_body_cells'=>PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm',
				'list_custom_head_cells'=>PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm',
				'list_custom_prepare_func'=>'bundle_prepare_product_list_data',
				'list_record_url'=>null,
				'list_search_enabled' => true,
				'list_no_setup_link'=>true,
				'list_search_fields'=> array('shop_products.name', 'shop_products.sku'),
				'list_search_prompt'=>'find products by name or SKU',
				'list_no_form'=>true,
				'list_top_partial'=>false,
				'list_name'=>'add_bundle_products_list',
				'list_items_per_page'=>10
			);
		}
		
		public function configure_products_controller($controller)
		{
			if (Phpr::$router->action != 'preview')
				return;
				
			$options = $this->bundle_get_product_popup_list_options();
			foreach ($options as $name=>$value)
				$controller->$name = $value;
		}
		
		public function bundle_prepare_product_list_data()
		{
			$item = $this->find_bundle_offer(post('bundle_current_item_id'));
			
			$obj = Shop_Product::create();
			$obj->where('grouped is null');

			$items_all = $item->list_related_records_deferred('items_all', $this->bundle_get_item_session_key($item->id));
			$product_ids = array();
			foreach ($items_all as $item)
				$product_ids[] = $item->product_id;

			if ($product_ids)
				$obj->where('id not in (?)', array($product_ids));

			return $obj;
		}
		
		public function preview_on_add_bundle_products($product_id)
		{
			try
			{
				$ids = post('list_ids', array());
				if (!count($ids))
					throw new Phpr_ApplicationException('Please select product(s) to add.');

				$item = $this->find_bundle_offer(post('bundle_current_item_id'));
				$item->add_products($ids, $this->bundle_get_item_session_key($item->id));
				
				$this->_controller->preparePartialRender('bundle_item_form');
				$this->preview_on_refresh_bundle_ui($product_id);
				
				$this->_controller->preparePartialRender('listadd_bundle_products_list');
				$this->_controller->onListReload();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_on_set_bundle_offer_item_order()
		{
			try
			{
				Shop_ProductBundleOfferItem::create()->set_item_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_on_remove_bundle_offer_items($product_id)
		{
			try
			{
				$ids = post('list_ids', array());
				if (!count($ids))
					throw new Phpr_ApplicationException('Please select product(s) to add.');

				$item = $this->find_bundle_offer(post('bundle_current_item_id'));
				$item->remove_products($ids, $this->bundle_get_item_session_key($item->id));
				
				$this->preview_on_refresh_bundle_ui($product_id);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function preview_on_save_bundle_offer_changes($product_id)
		{
			try
			{
				$master_product = $this->find_product($product_id);
				
				$items = $master_product->list_related_records_deferred('bundle_offers_link', $this->_controller->formGetEditSessionKey());
				foreach ($items as $item)
				{
					$products = $item->list_related_records_deferred('items_all', $this->bundle_get_item_session_key($item->id));

					$last_product_id = null;
					try
					{
						foreach ($products as $product)
						{
							if (!$this->is_product_data_set($product->id))
								continue;
							
							$last_product_id = $product->id;
							$product->disable_column_cache();

							$product->price_override_mode = $this->bundle_get_product_field($product->id, 'price_override_mode');
							$product->price_or_discount = $this->bundle_get_product_field($product->id, 'price_or_discount');
							$product->default_quantity = $this->bundle_get_product_field($product->id, 'default_quantity');
							$product->allow_manual_quantity = $this->bundle_get_product_field($product->id, 'allow_manual_quantity');
							$product->is_active = $this->bundle_get_product_field($product->id, 'is_active');
							$product->is_default = $this->bundle_get_product_field($product->id, 'is_default');
							$product->save(null);
						}
					} catch (exception $ex)
					{
						if ($ex instanceof Phpr_ValidationException)
						{
							$column = $ex->validation->errorFields[0];
							
							$result = array(
								'item'=>$item->id,
								'product'=>$last_product_id,
								'column'=>$column,
								'message'=>$ex->getMessage()
							);

							$message = json_encode($result);
							throw new Phpr_ApplicationException($message);

						} else throw $ex;
					}
				}
				
				foreach ($items as $item)
				{
					$item->save(null, $this->bundle_get_item_session_key($item->id));
				}

				/*
				 * Apply bundle items
				 */
				$master_product->save(null, $this->_controller->formGetEditSessionKey());
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function is_product_data_set($product_id)
		{
			$products_data = $this->bundle_get_post_storage()->get('bundle_product_data', array());
			return array_key_exists($product_id, $products_data);
		}
	}
	
?>