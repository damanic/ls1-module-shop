<?

	class Shop_Orders extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Db_FilterBehavior, Cms_PageSelector';
		public $list_model_class = 'Shop_Order';
		public $list_record_url = null;
		public $list_record_onclick = null;
		public $list_options = array();

		public $form_preview_title = 'Order Preview';
		public $form_create_title = 'New Order';
		public $form_edit_title = 'Edit Order';
		public $form_model_class = 'Shop_Order';
		public $form_not_found_message = 'Order not found';
		public $form_redirect = null;
		public $form_edit_save_redirect = null;
		public $form_create_save_redirect = null;
		public $enable_concurrency_locking = true;

		public $form_edit_save_flash = 'Order has been successfully saved';
		public $form_create_save_flash = 'Order has been successfully added';
		public $form_no_flash = false;

		public $list_search_enabled = true;
		public $list_search_fields = array('@id', 'concat(@billing_last_name, " ", @billing_first_name)', 'concat(@shipping_last_name, " ", @shipping_first_name)', '@billing_email', '@billing_company', '@shipping_company', '@shipping_street_addr', '@billing_street_addr', '@billing_city', '@shipping_city', 'billing_country_calculated_join.name', 'shipping_country_calculated_join.name', 'billing_state_calculated_join.name', 'shipping_state_calculated_join.name');
		public $list_search_prompt = 'find orders by #, name, company or email';

		public $list_custom_head_cells = null;
		public $list_custom_body_cells = null;
		public $list_custom_prepare_func = null;
		public $list_no_setup_link = false;
		public $list_items_per_page = 20;
		public $list_name = null;
		public $list_top_partial = null;
		public $list_sidebar_panel = null;
		public $list_cell_individual_partial = array('has_notes' => 'has_notes');
		
		public $list_render_filters = false;
		public $filter_list_title = 'Filter orders';
		
		public $csv_import_file_columns_header = 'File Columns';
		public $csv_import_db_columns_header = 'LemonStand Order Columns';
		public $csv_import_data_model_class = 'Shop_Order';
		public $csv_import_config_model_class = 'Shop_OrderCsvImportModel';
		public $csv_import_name = 'Order import';
		public $csv_import_url = null;
		public $csv_import_short_name = 'Orders';
		public $include_preview_breadcrumb = false;
		
		protected $refererUrl = null;
		protected $refererName = null;
		protected $refererObj = false;
		
		public $filter_filters = array(
			'status'=>array('name'=>'Current Order Status', 'class_name'=>'Shop_OrderStatusFilter', 'prompt'=>'Please choose order statuses you want to include to the list. Orders with other statuses will be hidden.', 'added_list_title'=>'Added Statuses'),
			'products'=>array('name'=>'Product', 'class_name'=>'Shop_ProductFilter', 'prompt'=>'Please choose products you want to include to the list. Orders which do not contain selected products will be hidden.', 'added_list_title'=>'Added Products'),
			'categories'=>array('name'=>'Category', 'class_name'=>'Shop_CategoryFilter', 'prompt'=>'Please choose product categories you want to include to the list. Orders which do not contain products from selected categories will be excluded.', 'added_list_title'=>'Added Categories'),
			'groups'=>array('name'=>'Product group', 'class_name'=>'Shop_CustomGroupFilter', 'cancel_if_all'=>false, 'prompt'=>'Please choose product groups you want to include to the list. Orders which do not contain products from selected categories will be excluded.', 'added_list_title'=>'Added Groups'),
			'product_types'=>array('name'=>'Product type', 'class_name'=>'Shop_ProductTypeFilter', 'prompt'=>'Please choose product types you want to include to the list. Orders which do not contain products of selected types will be excluded.', 'added_list_title'=>'Added Types'),
			'order_deleted_status'=>array('name'=>'Deleted status', 'class_name'=>'Shop_DeletedFilter', 'prompt'=>'Please choose whether you want to see only deleted or only active orders.', 'added_list_title'=>'Added Statuses'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'coupons'=>array('name'=>'Coupon', 'class_name'=>'Shop_CouponFilter', 'prompt'=>'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title'=>'Added Coupons', 'cancel_if_all'=>false),
			'billing_country'=>array('name'=>'Billing country', 'class_name'=>'Shop_OrderBillingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries')
		);
		
		public $filter_switchers = array();
		
		public $filter_onApply = 'listReload();';
		public $filter_onRemove = 'listReload();';
		protected $processed_customer_ids = array();

		public $globalHandlers = array(
			'onCopyBillingAddress', 
			'onBillingCountryChange',
			'onShippingCountryChange',
			'onLoadItemForm',
			'onLoadFindProductForm',
			'onLoadFindBundleProductForm',
			'onAddProduct',
			'onDeleteItem',
			'onUpdateProductId',
			'onAddItem',
			'onUpdateItem',
			'onUpdateItemList',
			'onUpdateShippingOptions',
			'onUpdateBillingOptions',
			'onUpdateTotals',
			'onUpdateItemPriceAndDiscount',
			'onCalculateDiscounts',
			'onLoadDiscountForm',
			'onApplyDiscount',
			'onCustomEvent',
			'onUpdateBundleProductList',
			'onUnlockOrder',
			'onLockOrder',
			'onToggleOrderDoc',
			'onCopyOrder'
		);

		protected $required_permissions = array('shop:manage_orders_and_customers');

		public function __construct()
		{
			$this->addPublicAction('rss');
			$this->addJavascript('/modules/shop/resources/javascript/print_this.js');
			
			if (Phpr::$router->action == 'import_csv' || post('import_csv_flag') || Phpr::$router->action == 'import_csv_get_config')
				$this->implement .= ', Backend_CsvImport';
			
			Backend::$events->fireEvent('shop:onConfigureOrdersPage', $this);
		
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'orders';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/orders/preview/');
			$this->form_redirect = url('/shop/orders');
			$this->form_edit_save_redirect = url('/shop/orders/preview/%s?'.uniqid());
			$this->form_create_save_redirect = url('/shop/orders/preview/%s?'.uniqid());
			
			$invoice_mode = Phpr::$router->param('param1') == 'invoice';
			$parent_order_id = Phpr::$router->param('param2');

			if ($invoice_mode)
				$this->form_redirect = url('/shop/orders/preview/'.$parent_order_id);

			if (post('find_product_mode'))
			{
				$this->list_model_class = 'Shop_Product';
				$this->list_columns = array('name', 'sku', 'total_in_stock', 'price');
	
				$this->list_custom_prepare_func = 'prepare_product_list';
				$this->list_record_url = null;
				
				$edit_session_key = $this->formGetEditSessionKey();
				$this->list_record_onclick = "
					new PopupForm('onAddProduct', 
						{
							ajaxFields: {'product_id': '%s', 'edit_session_key': '$edit_session_key', 'customer_id': $('Shop_Order_customer_id') ? $('Shop_Order_customer_id').value : -1}
						});
				
					return false;
				";
				$this->list_search_enabled = true;
				$this->list_no_setup_link = true;

				$this->list_search_fields = array('shop_products.name', 'shop_products.sku', '(select group_concat(sku) from shop_products sku_list where sku_list.product_id is not null and sku_list.product_id=shop_products.id)', '(select group_concat(sku) from shop_option_matrix_records where product_id=shop_products.id)');
				$this->list_search_prompt = 'find products by name or SKU';
				$this->list_items_per_page = 10;
				
				$this->list_custom_head_cells = false;
				$this->list_custom_body_cells = false;
				$this->list_top_partial = null;
			} else
			{
				$this->list_top_partial = 'order_selectors';
			}
			
			if (post('filter_request'))
				$this->list_top_partial = null;

			if (Shop_Order::invoice_system_supported())
			{
				$this->filter_switchers = array(
					'display_invoices'=>array('name'=>'Display invoices', 'class_name'=>'Shop_DisplayInvoicesSwitcher')
				);
			}
			
			if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer')
				$this->form_create_save_redirect = url('/shop/customers/preview/'.Phpr::$router->param('param2'));
			
			Backend::$events->fireEvent('shop:onDisplayOrdersPage', $this);
			
			$this->addRss(url('/shop/orders/rss'));
		}
		
		public function index()
		{
			$this->app_page_title = 'Orders';
		}

		public function listGetRowClass($model)
		{
			$classes = '';
			$classes .= $model->deleted_at ? 'deleted' : 'order_active';
			$classes .= ' status_'.$model->status_id;
			$classes .= $model->parent_order_id ? ' invoice' : null;
			return $classes;
		}
		
		public function listPrepareData()
		{
			$obj = Shop_Order::create();
			
			$status_id = $this->getCurrentOrderStatus();
			if ($status_id)
			{
				$obj->where('status_id=?', $status_id);
				if (isset($this->filter_filters['status']))
					unset($this->filter_filters['status']);
			}
			
			$this->filterApplyToModel($obj);

			return $obj;
		}
		
		protected function getCurrentOrderStatus()
		{
			$status_id = Db_UserParameters::get('orderlist_status');
			if (!strlen($status_id))
				return null;

			$status = Shop_OrderStatus::create()->find($status_id);
			if (!$status)
				return null;

			return $status_id;
		}
		
		protected function index_onSelectOrderStatus()
		{
			Db_UserParameters::set('orderlist_status', post('sidebar_order_status_id'));
			$this->listResetPage();
			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onHideStatusSelector()
		{
			Db_UserParameters::set('orderlist_status', null);
			Db_UserParameters::set('orderlist_stsl_visible', false);
			$this->listResetPage();
			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onShowStatusSelector()
		{
			Db_UserParameters::set('orderlist_stsl_visible', true);
			$this->listResetPage();
			$this->renderPartial('orders_page_content');
		}
		
		protected function orderStatusSelectorVisible()
		{
			return Db_UserParameters::get('orderlist_stsl_visible', null, true);
		}
		
		protected function evalOrderNum()
		{
			return Shop_Order::create()->requestRowCount();
		}
		
		protected function index_onRefresh()
		{
			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onResetFilters()
		{
			$this->filterReset();
			$this->listCancelSearch();
			Phpr::$response->redirect(url('shop/orders'));
		}
		
		protected function index_onLoadDeleteOrdersForm()
		{
			try
			{
				$order_ids = post('list_ids', array());
				
				if (!count($order_ids))
					throw new Phpr_ApplicationException('Please select order(s) to delete.');
				
				$this->viewData['order_count'] = count($order_ids);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('delete_orders_form');
		}

		protected function index_onDeleteSelected()
		{
			$orders_processed = 0;
			$order_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $order_ids;

			foreach ($order_ids as $order_id)
			{
				$order_id = trim($order_id);
				if (!strlen($order_id))
					continue;
				
				$order = null;
				try
				{
					$order = Shop_Order::create()->find($order_id);
					if (!$order)
						throw new Phpr_ApplicationException('Order with identifier '.$order_id.' not found.');

					$order->delete_order();

					$orders_processed++;
				}
				catch (Exception $ex)
				{
					if (!$order)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting order "'.$order->id.'": '.$ex->getMessage();

					break;
				}
			}

			if ($orders_processed)
			{
				if ($orders_processed > 1)
					Phpr::$session->flash['success'] = $orders_processed.' orders have been successfully marked as deleted.';
				else
					Phpr::$session->flash['success'] = '1 order has been successfully marked as deleted.';
			}

			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onDeleteSelectedPermanently()
		{
			$orders_processed = 0;
			$order_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $order_ids;

			foreach ($order_ids as $order_id)
			{
				$order_id = trim($order_id);
				if (!strlen($order_id))
					continue;
				
				$order = null;
				try
				{
					$order = Shop_Order::create()->find($order_id);
					if ($order)
						$order->delete();

					$orders_processed++;
				}
				catch (Exception $ex)
				{
					if (!$order)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting order "'.$order->id.'": '.$ex->getMessage();

					break;
				}
			}

			if ($orders_processed)
			{
				if ($orders_processed > 1)
					Phpr::$session->flash['success'] = $orders_processed.' orders have been successfully deleted.';
				else
					Phpr::$session->flash['success'] = '1 order has been successfully deleted.';
			}

			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onRestoreSelected()
		{
			$orders_processed = 0;
			$order_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $order_ids;

			foreach ($order_ids as $order_id)
			{
				$order = null;
				try
				{
					$order = Shop_Order::create()->find($order_id);
					if (!$order)
						throw new Phpr_ApplicationException('Order with identifier '.$order_id.' not found.');

					$order->restore_order();

					$orders_processed++;
				}
				catch (Exception $ex)
				{
					if (!$order)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error restoring order "'.$order->id.'": '.$ex->getMessage();

					break;
				}
			}

			if ($orders_processed)
			{
				if ($orders_processed > 1)
					Phpr::$session->flash['success'] = $orders_processed.' orders have been successfully restored.';
				else
					Phpr::$session->flash['success'] = '1 order has been successfully restored.';
			}

			$this->renderPartial('orders_page_content');
		}
		
		protected function index_onLoadChangeStatusForm()
		{
			$order_ids = post('list_ids', array());
			$this->viewData['orders'] = array();
			$orders = array();

			try
			{
				foreach ($order_ids as $order_id)
				{
					$order = Shop_Order::create()->find($order_id);
					if (!$order)
						throw new Phpr_ApplicationException('Order with identifier '.$order_id.' not found.');
					
					$orders[] = $order;
				}
				
				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found.');
					
				$from_state_ids = array();
				foreach ($orders as $order)
					$from_state_ids[$order->status_id] = 1;
					
				$from_state_ids = array_keys($from_state_ids);
				$end_transitions = Shop_StatusTransition::listAvailableTransitionsMulti($this->currentUser->shop_role_id, $from_state_ids);

				$log_record = Shop_OrderStatusLog::create();
				$log_record->init_columns_info();
				$log_record->define_form_fields('multiorder');
				$log_record->role_id = $this->currentUser->shop_role_id;
				$log_record->status_ids = $from_state_ids;

				$this->viewData['log_record'] = $log_record;
				$this->viewData['end_transitions'] = $end_transitions;
				
				$log_record->set_default_email_notify_checkbox();				
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->viewData['orders'] = $orders;
			$this->viewData['order_ids'] = $order_ids;
			$this->renderPartial('change_status_form');
		}
		
		protected function index_onSetOrderStatuses()
		{
			$orders_processed = 0;

			try
			{
				$data = post('Shop_OrderStatusLog', array());
				if (!strlen($data['status_id']))
					throw new Phpr_ApplicationException('Please select order status.');

				$order_ids = post('order_ids');
				if (!strlen($order_ids))
					throw new Phpr_ApplicationException('Orders not found.');
					
				$order_ids = explode(',', $order_ids);
				$this->viewData['list_checked_records'] = $order_ids;
				
				@set_time_limit(600);

				foreach ($order_ids as $order_id)
				{
					$order_id = trim($order_id);
					try
					{
						$order = Shop_Order::create()->find($order_id);
						if (!$order)
							throw new Phpr_ApplicationException('not found');

						if ($data['status_id'] == $order->status_id)
							throw new Phpr_ApplicationException('new order status should not match current order status.');

						if (!Shop_StatusTransition::listAvailableTransitions($this->currentUser->shop_role_id, $order->status_id, $data['status_id'])->count)
							throw new Phpr_ApplicationException('you cannot transfer the order to the selected status.');

						Shop_OrderStatusLog::create_record($data['status_id'], $order, $data['comment'], $data['send_notifications'], $data);
						$orders_processed++;
					}
					catch (Exception $ex)
					{
						Phpr::$session->flash['error'] = 'Order #'.$order_id.': '.$ex->getMessage();
						break;
					}
				}
				
				if ($orders_processed)
				{
					Db_UserParameters::set('orders_email_on_status_change', $data['send_notifications']);
					if ($orders_processed > 1)
						Phpr::$session->flash['success'] = $orders_processed.' orders have been successfully updated.';
					else
						Phpr::$session->flash['success'] = '1 order has been successfully updated.';
				}

				$this->renderPartial('orders_page_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function index_onPrintInvoice()
		{
			try
			{
				$order_ids = post('list_ids', array());

				if (!count($order_ids))
					throw new Phpr_ApplicationException('Please select orders to print invoice for.');

				$order_ids = implode('|', $order_ids);
				Phpr::$response->redirect(url('shop/orders/invoice/'.$order_ids));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function index_onPrintDocs()
		{
			try
			{
				$order_ids = post('list_ids', array());

				if (!count($order_ids))
					throw new Phpr_ApplicationException('Please select orders to print documents for.');

//				$order_ids = implode('|', $order_ids);
//				Phpr::$response->redirect(url('shop/orders/invoice/'.$order_ids));

				$order_id_string = implode('|', $order_ids);
				$this->orderdoc($order_id_string, null);
				$this->renderPartial('popup_print_orderdoc');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onPrintPackingSlip()
		{
			try
			{
				$order_ids = post('list_ids', array());
				$page_breaks_rule = post('page_breaks_rule', null);

				if (!count($order_ids))
					throw new Phpr_ApplicationException('Please select orders to print packing slip for.');

				$order_ids = implode('|', $order_ids);
				Phpr::$response->redirect(url('shop/orders/packing_slip/'.$order_ids.'/'.$page_breaks_rule));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onPrintShippingLabel()
		{
			try
			{
				$order_ids = post('list_ids', array());

				if (!count($order_ids))
					throw new Phpr_ApplicationException('Please select orders to print shipping labels for.');

				$order_ids = implode('|', $order_ids);
				Phpr::$response->redirect(url('shop/orders/shipping_label/'.$order_ids.'/'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}


		public function export_orders($format = null)
		{
			$this->list_name = 'Shop_Orders_index_list';
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('orders.csv', $options, null, true);
		}
		
		public function export_orders_and_products($format = null)
		{
			$this->list_name = 'Shop_Orders_index_list';
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('orders.csv', $options, null, true, array('headerCallback' => array('Shop_Order', 'export_orders_and_products_header'), 'rowCallback' => array('Shop_Order', 'export_orders_and_products_row')));
		}

		public function export_customers($format = null)
		{
			$this->list_name = 'Shop_Orders_index_list';
			$this->listExportCsv('customers.csv', array(
				'iwork'=>$format == 'iwork',
				'list_sorting_column'=>'billing_email',
				'list_columns'=>array(
					'billing_email', 
					'billing_first_name', 
					'billing_last_name', 
					'billing_phone', 
					'billing_country', 
					'billing_state', 
					'billing_street_addr', 
					'billing_city', 
					'billing_zip')
			), array($this, 'filter_customer_records'), true);
		}
		
		public function import_csv()
		{
			$this->app_page_title = 'Import Orders';
		}

		public function filter_customer_records($row)
		{
			if (in_array($row->customer_id, $this->processed_customer_ids))
				return false;
			
			$this->processed_customer_ids[] = $row->customer_id;
			return true;
		}

		/*
		 * Preview
		 */
		
		public function preview_formBeforeRender($order)
		{
			$referer = $this->viewData['referer'] = $this->getReferer();
			$this->app_page = $this->getRefererTab();
			$this->form_no_flash = true;
		}
		
		protected function preview_onLoadItemPreview()
		{
			try
			{
				$item = Shop_OrderItem::create()->find(post('item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Item not found');

				if (!$item->product)
					throw new Phpr_ApplicationException('Item product not found');

				$item->define_form_fields('preview');
				$this->viewData['item'] = $item;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('order_item_preview');
		}

		protected function preview_onPrintOrderDoc($order_id){
			try
			{
				$order = $this->getOrderObj($order_id);
				$variant = post('variant');

				if (!$order)
					throw new Phpr_ApplicationException('Order not found');

				$orders = array($order);
				$this->_orderdoc_add_viewdata($orders,$variant);
				$this->viewData['order_id_string'] = $order_id;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('popup_print_orderdoc');
		}

		protected function preview_onLoadPaymentDetailsPreview()
		{
			try
			{
				$record = Shop_PaymentLogRecord::create()->find(post('record_id'));
				if (!$record)
					throw new Phpr_ApplicationException('Record not found');

				$record->define_form_fields();
				$this->viewData['trackTab'] = false;
				$this->viewData['record'] = $record;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('payment_attempt_preview');
		}
		
		protected function preview_onLoadPaymentTransactionPreview()
		{
			try
			{
				$record = Shop_PaymentTransaction::create()->find(post('record_id'));
				if (!$record)
					throw new Phpr_ApplicationException('Transaction not found');
					
				if (!$record->payment_method)
					throw new Phpr_ApplicationException('Payment method not found');

				$record->define_form_fields();
				$this->viewData['trackTab'] = false;
				$this->viewData['record'] = $record;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('payment_transaction_preview');
		}
		
		protected function preview_onRestoreOrder($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);
				$order->restore_order();

				Phpr::$session->flash['success'] = 'Order has been restored.';
				Phpr::$response->redirect(url('/shop/orders/preview/'.$order->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onDeleteOrderPermanently($order_id)
		{
			if ( !$this->currentUser->get_permission( 'shop', 'delete_orders' ) ) {
				throw new Phpr_ApplicationException( 'You do not have permission to permanently delete orders' );
			}
			try
			{
				$order = $this->getOrderObj($order_id);
				$order->delete();

				Phpr::$session->flash['success'] = 'Order has been successfully deleted.';
				Phpr::$response->redirect(url('/shop/orders/?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onLoadMessageTemplateForm($order_id)
		{
			try
			{
				$this->viewData['templates'] = System_EmailTemplate::create()->order('code')->where('(is_system is null or is_system=0)')->find_all();
				$this->viewData['order_id'] = $order_id;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('message_select_template');
		}

		protected function preview_onLoadNoteForm($order_id)
		{
			$this->viewData['note'] = Shop_OrderNote::create();
			$this->viewData['note']->define_form_fields();
			$this->viewData['users'] = Users_User::list_users_having_permission('shop', 'manage_orders_and_customers');
			
			$reply_note = null;
			
			$reply_note_id = post('reply_note_id');
			if ($reply_note_id)
				$reply_note = Shop_OrderNote::create()->find($reply_note_id);

			$this->viewData['reply_note'] = $reply_note;
			$this->viewData['reply_user_id'] = $reply_note ? $reply_note->created_user_id : null;
			$this->renderPartial('add_note_form');
		}
		
		protected function preview_onSaveNote($order_id)
		{
			try
			{
				$note = Shop_OrderNote::create();
				$note->init_columns_info();
				$note->define_form_fields();
				$note->order_id = $order_id;
				$note->notification_users = post('notification_users', array());
				$note->save(post('Shop_OrderNote'), $this->formGetEditSessionKey());

				$this->viewData['form_model'] = $this->getOrderObj($order_id);
				$this->renderPartial('order_notes');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onLoadNotePreview()
		{
			try
			{
				$note = Shop_OrderNote::create()->find(post('note_id'));
				if (!$note)
					throw new Phpr_ApplicationException('Note not found');
					
				$note->init_columns_info();
				$note->define_form_fields('preview');
				$this->viewData['note'] = $note;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('order_note_preview');
		}
		
		protected function preview_onUpdateInvoiceList($order_id)
		{
			try
			{
				$this->viewData['form_model'] = $this->getOrderObj($order_id);
				$this->renderPartial('order_invoices');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onDeleteOrder($order_id)
		{
			$this->edit_onDeleteOrder($order_id, url('/shop/orders/preview/'.$order_id));
		}
		
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

			return $this->refererName = 'Order List';
		}

		protected function getRefererUrl()
		{
			if ($this->refererUrl != null)
				return $this->refererUrl;
				
			$referer = $this->getReferer();
			$refererObj = $this->getRefererObj($referer);
			if ($refererObj && method_exists($refererObj, 'refererUrl'))
				return $this->refererUrl = $refererObj->refererUrl();
			
			return $this->refererUrl = url('/shop/orders');
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

		/*
		 * Invoice
		 */
		
		public function invoice($order_id)
		{
			try
			{
				if (strpos($order_id, '|') !== false)
				{
					$order_id = explode('|', $order_id);
					$identifiers = array();
					foreach ($order_id as $id)
					{
						if (strlen($id))
							$identifiers[] = $id;
					}
				} else
					$order_id = array($order_id);
					
				if (count($order_id) == 1)
					$this->app_page_title = 'Invoice #'.$order_id[0];
				else
				{
					if (count($order_id) > 5)
						$this->app_page_title = 'Invoice - multiple orders';
					else
						$this->app_page_title = 'Invoice #'.implode(', ', $order_id);
				}
				
				$this->viewData['invoice_template_css'] = array();

				$orders = array();
				foreach ($order_id as $id)
				{
					try
					{
						$order = $this->formFindModelObject($id);
						$orders[] = $order;
					} catch (exception $ex) {}
				}
				
				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found');

				$this->viewData['orders'] = $orders;
				$company_info = $this->viewData['company_info'] = Shop_CompanyInformation::get();
				$invoice_info = $this->viewData['invoice_template_info'] = $company_info->get_invoice_template();
				$this->viewData['template_id'] = isset($invoice_info['template_id']) ? $invoice_info['template_id'] : null;
				$this->viewData['invoice_template_css'] = isset($invoice_info['css']) ? $invoice_info['css'] : array();
				$this->viewData['display_due_date'] = strlen($company_info->invoice_due_date_interval);
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		/*
		 * Returns raw document output without surrounding CMS layout
		 */

		public function document($order_id_string, $variant)
		{
			try {
				$this->layoutsPath = PATH_APP.'/modules/shop/layouts';
				$this->layout = 'document';
				$orders = $this->_orderdoc_get_orders($order_id_string);

				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found');

				if (count($orders) == 1)
					$this->app_page_title = 'Document '.$variant.': Order #'.$orders[0]->id;
				else {
					$this->app_page_title = 'Document '.$variant.': Multiple orders';
				}
				$this->viewData['order_id_string'] = $order_id_string;
				$this->_orderdoc_add_viewdata($orders, $variant);

			}
			catch (exception $ex) {
				$this->handlePageError($ex);
			}
		}

		/*
		 * Commercial Document Viewer
		 */

		public function orderdoc($order_id_string, $variant)
		{
			try {
				$orders = $this->_orderdoc_get_orders($order_id_string);

				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found');

				if (count($orders) == 1)
					$this->app_page_title = 'Docs: Order #'.$orders[0]->id;
				else {
					$this->app_page_title = 'Docs: Multiple orders';
				}
				$this->viewData['order_id_string'] = $order_id_string;
				$this->_orderdoc_add_viewdata($orders, $variant);
			}
			catch (exception $ex) {
				$this->handlePageError($ex);
			}
		}

		private function _orderdoc_get_orders($order_id_string) {
			$orders = array();
			if ( strpos( $order_id_string, '|' ) !== false ) {
				$order_ids = explode( '|', $order_id_string );
			} else {
				$order_ids = array( $order_id_string );
			}

			$orders = array();
			foreach ( $order_ids as $id ) {
				if ( is_numeric( $id ) ) {
					try {
						$order    = $this->formFindModelObject( $id );
						$orders[] = $order;
					} catch ( exception $ex ) {
					}
				}
			}

			return $orders;
		}

		private function _orderdoc_add_viewdata($orders, $variant){
			$this->viewData['orders'] = $orders;
			$company_info = $this->viewData['company_info'] = Shop_CompanyInformation::get();
			$this->viewData['template_info'] = $this->viewData['company_info']->get_invoice_template();
			$this->viewData['custom_render'] = isset($this->viewData['template_info']['custom_render']) ? $this->viewData['template_info']['custom_render'] : false;
			$this->viewData['active_variant'] = empty($variant) ? Shop_OrderDocsHelper::get_default_variant($orders,$this->viewData['template_info']): $variant;
			$this->viewData['applicable_variants'] = Shop_OrderDocsHelper::get_applicable_variants($orders, $this->viewData['template_info'] );
			$this->viewData['display_due_date'] = strlen($company_info->invoice_due_date_interval);
			$this->viewData['auto_print'] = (count($this->viewData['applicable_variants']) < 2) ? true : false ;

			$this->viewData['css'] = array();
			$this->viewData['css_files'] = array();
			foreach ($this->viewData['template_info']['css'] as $src => $media){
				$href = (strpos($src, '/') === false) ? '/modules/shop/invoice_templates/'.$this->viewData['template_info']['template_id'].'/resources/css/'.$src : $src;
				$this->viewData['css'][] = array(
					'media' => $media,
					'href' => $href
				);
				$this->viewData['css_files'][] = $href;
			}
		}

		protected function onToggleOrderDoc(){
			$variant = post('variant');
			$order_id_string = post('order_id_string');
			$this->orderdoc($order_id_string,$variant);
			$this->renderMultiple(array(
				'orderdoc_viewer'=>'@_orderdoc_viewer',
			));
		}
		
		/*
		 * Packing slip
		 */
		
		public function packing_slip($order_id, $page_breaks_rule)
		{
			try
			{
				if (strpos($order_id, '|') !== false)
				{
					$order_id = explode('|', $order_id);
					$identifiers = array();
					foreach ($order_id as $id)
					{
						if (strlen($id))
							$identifiers[] = $id;
					}
				} else
					$order_id = array($order_id);
					
				if (count($order_id) == 1)
					$this->app_page_title = 'Packing slip #'.$order_id[0];
				else
				{
					if (count($order_id) > 5)
						$this->app_page_title = 'Packing slip - multiple orders';
					else
						$this->app_page_title = 'Packing slip #'.implode(', ', $order_id);
				}
				
				$orders = array();
				foreach ($order_id as $id)
				{
					try
					{
						$order = $this->formFindModelObject($id);
						$orders[] = $order;
					} catch (exception $ex) {}
				}
				
				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found');
				
				$this->viewData['page_breaks_rule'] = $page_breaks_rule;
				$this->viewData['slip_template_css'] = array();
				$company_info = $this->viewData['company_info'] = Shop_CompanyInformation::get();
				$slip_info = $this->viewData['slip_template_info'] = $company_info->get_packing_slip_template();
				$this->viewData['slip_template_css'] = isset($slip_info['css']) ? $slip_info['css'] : array();
				$this->viewData['template_id'] = isset($slip_info['template_id']) ? $slip_info['template_id'] : null;
				$this->viewData['orders'] = $orders;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		/*
		 * Shipping Label
		 */
		
		public function shipping_label($order_id)
		{
			try
			{
				if (strpos($order_id, '|') !== false)
				{
					$order_id = explode('|', $order_id);
					$identifiers = array();
					foreach ($order_id as $id)
					{
						if (strlen($id))
							$identifiers[] = $id;
					}
				} else
					$order_id = array($order_id);
					
				if (count($order_id) == 1)
					$this->app_page_title = 'Shipping Label #'.$order_id[0];
				else
				{
					if (count($order_id) > 5)
						$this->app_page_title = 'Shipping label - multiple orders';
					else
						$this->app_page_title = 'Shipping label #'.implode(', ', $order_id);
				}
				
				$orders = array();
				foreach ($order_id as $id)
				{
					try
					{
						$order = $this->formFindModelObject($id);
						$orders[] = $order;
					} catch (exception $ex) {}
				}
				
				if (!count($orders))
					throw new Phpr_ApplicationException('No orders found');
				
				$this->viewData['label_template_css'] = array();
				$company_info = $this->viewData['company_info'] = Shop_CompanyInformation::get();
				$this->viewData['shipping_params'] = $shipping_params = Shop_ShippingParams::get();
				$this->viewData['origin_country'] = Shop_Country::create()->find_by_id($shipping_params->country_id);
				$this->viewData['origin_state'] = Shop_CountryState::create()->find_by_id($shipping_params->state_id);

				$label_info = $this->viewData['label_template_info'] = $company_info->get_shipping_label_template();
				$this->viewData['label_template_css'] = isset($label_info['css']) ? $label_info['css'] : array();
				$this->viewData['template_id'] = isset($label_info['template_id']) ? $label_info['template_id'] : null;
				$this->viewData['orders'] = $orders;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		/*
		 * Change order status
		 */
		
		public function change_status($order_id)
		{
			$this->viewData['order_id'] = $order_id;
			$this->app_page_title = 'Change Order Status';
			
			try
			{
				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');
					
				$log_record = Shop_OrderStatusLog::create();
				$log_record->init_columns_info();
				$log_record->define_form_fields();
				$log_record->role_id = $this->currentUser->shop_role_id;
				$log_record->status_id = $order->status_id;

				$this->viewData['log_record'] = $log_record;
				$log_record->set_default_email_notify_checkbox();

				$this->viewData['order'] = $order;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function change_status_onSave($order_id)
		{
			try
			{
				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');

				$data = post('Shop_OrderStatusLog', array());
				if (!strlen($data['status_id']))
					throw new Phpr_ApplicationException('Please select order status.');
				
				if ($data['status_id'] == $order->status_id)
					throw new Phpr_ApplicationException('New order status should not match current order status.');

				if (!Shop_StatusTransition::listAvailableTransitions($this->currentUser->shop_role_id, $order->status_id, $data['status_id'])->count)
					throw new Phpr_ApplicationException('You cannot transfer the order to the selected status.');

				Shop_OrderStatusLog::create_record($data['status_id'], $order, $data['comment'], $data['send_notifications'], $data);
				Db_UserParameters::set('orders_email_on_status_change', $data['send_notifications']);

				Phpr::$session->flash['success'] = 'Order status has been successfully changed';
				Phpr::$response->redirect(url('/shop/orders/preview/'.$order_id));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Update order transaction status
		 */
		
		public function update_transaction_status($order_id)
		{
			$this->viewData['order_id'] = $order_id;
			$this->app_page_title = 'Update Transaction Status';

			try
			{

				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');

				//fetch latest transaction statuses
				$unique_transactions = Shop_PaymentTransaction::get_unique_transactions($order);
				Shop_PaymentTransaction::request_transactions_update($order,$unique_transactions);

				$current_transaction = $order->payment_transactions[0];
				if (!$current_transaction)
					throw new Phpr_ApplicationException('Current order transaction status not found');
					
				$order_transitions = Shop_StatusTransition::listAvailableTransitions($this->currentUser->shop_role_id, $order->status_id);

				$this->viewData['current_transaction'] = $current_transaction;
				$this->viewData['unique_transactions'] = $unique_transactions;
				$this->viewData['order'] = $order;
				$this->viewData['order_transitions'] = $order_transitions;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function update_transaction_status_onUpdate($order_id)
		{
			try
			{
				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');

				$transaction_id = post('transaction_record_id', false);
				if (!is_numeric($transaction_id))
					throw new Phpr_ApplicationException('Please select a transaction');

				$transaction = Shop_PaymentTransaction::create()->find($transaction_id);
				if (!$transaction)
					throw new Phpr_ApplicationException('Selected transaction not found');

				$new_transaction_status = post('new_transaction_status', false);
				if (!$transaction)
					throw new Phpr_ApplicationException('Please select a transaction status');

				$transaction->update_transaction_status($order, $new_transaction_status, post('new_order_status'), post('user_note'));

				Phpr::$session->flash['success'] = 'Transaction status has been successfully changed';
				Phpr::$response->redirect(url('/shop/orders/preview/'.$order_id).'#tab_5');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function update_transaction_status_onTransactionChange($order_id)
		{
			try
			{
				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');

				$transaction_id = post('transaction_record_id', false);
				if (!is_numeric($transaction_id))
					throw new Phpr_ApplicationException('Please select a transaction');

				$transaction = Shop_PaymentTransaction::create()->find($transaction_id);
				if (!$transaction)
					throw new Phpr_ApplicationException('Selected transaction not found');

				$order_transitions = Shop_StatusTransition::listAvailableTransitions($this->currentUser->shop_role_id, $order->status_id);

				$this->viewData['order_id'] = $order_id;
				$this->viewData['current_transaction'] = $transaction;
				$this->viewData['order'] = $order;
				$this->viewData['order_transitions'] = $order_transitions;
				$this->renderMultiple(array(
					'transaction_update_fields'=>'@_form_area_update_transaction_status',
				));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onRequestTransactionStatus($order_id)
		{
			try
			{
				$order = Shop_Order::create()->find($order_id);
				if (!$order)
					throw new Phpr_ApplicationException('Order not found');
					
				$has_transactions = $order->payment_transactions[0];
				if (!$has_transactions)
					throw new Phpr_ApplicationException('No transactions found');

				Shop_PaymentTransaction::request_transactions_update($order);

				$order = Shop_Order::create()->find($order_id);
				$this->viewData['form_model'] = $order;
				$this->renderMultiple(array(
					'payment_transaction_list'=>'@_payment_transaction_list',
					'order_payment_status'=>'@_order_payment_status'
				));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Create order
		 */
		
		protected function create_onCustomerChanged()
		{
			try
			{
				$form_model = $this->formCreateModelObject();
				$data = post('Shop_Order', array());
				$form_model->set_form_data($data);
				if (strlen($data['customer_id']))
				{
					$customer = Shop_Customer::create()->find($data['customer_id']);

					if (!$customer)
						throw new Phpr_ApplicationException('Customer not found');
						
					$customer->copy_to_order($form_model);

					if ($form_model->is_new_record())
					{
						echo ">>tab_3<<";
						$this->formRenderFormTab($form_model, 2);
						echo '<div class="clear"></div>';
						echo ">>tab_4<<";
						$this->formRenderFormTab($form_model, 3);
						echo '<div class="clear"></div>';
					} else
					{
						echo ">>tab_2<<";
						$this->formRenderFormTab($form_model, 1);
						echo '<div class="clear"></div>';
						echo ">>tab_3<<";
						$this->formRenderFormTab($form_model, 2);
						echo '<div class="clear"></div>';
					}
				}
				
				$this->renderOrderTotals($form_model);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function edit_formBeforeRender($model)
		{
			$model->shipping_sub_option_id = $model->shipping_method_id.'_'.md5($model->shipping_sub_option);
			if(!$model->has_shipping_quote_override()) {
				$model->manual_shipping_quote = $model->shipping_quote;
			}
		}

		public function create_formBeforeRender($model)
		{
			if (!$this->currentUser->role->can_create_orders)
				throw new Phpr_ApplicationException('You have no rights to create orders.');

			$countries = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 order by name limit 0,1');
			if (count($countries))
			{
				$firstCountry = $countries[0];
				$model->billing_country_id = $firstCountry->id;
				$model->shipping_country_id = $firstCountry->id;
				$states = Shop_CountryState::create()->order('name')->where('country_id=?', $firstCountry->id)->find_all();
				if ($states->count)
				{
					$model->billing_state_id = $states[0]->id;
					$model->shipping_state_id = $states[0]->id;
				}
			}

			//currency
			if ( $currency_id = filter_input( INPUT_GET, 'currency_id' ) ) {
				$currencies = new Shop_CurrencySettings();
				$currency = $currencies->find( $currency_id );
				if($currency){
					$model->set_currency($currency->code);
				}
			}
			
			if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer')
			{
				$model->customer_id = Phpr::$router->param('param2');
				
				$customer = Shop_Customer::create()->find($model->customer_id);
				if ($customer)
					$customer->copy_to_order($model);
			}
			
			/*
			 * Add invoice items
			 */

			$invoice_mode = Phpr::$router->param('param1') == 'invoice';
			$parent_order_id = Phpr::$router->param('param2');
			if ($invoice_mode && $parent_order_id)
			{
				$parent_order = Shop_Order::create()->find($parent_order_id);
				if (!$parent_order)
					throw new Phpr_ApplicationException('Parent order not found.');
					
				$items = array();
				foreach ($parent_order->items as $item)
				{
					$new_item = Shop_OrderItem::create()->copy_from($item);
					$result = Backend::$events->fireEvent('shop:onNewInvoiceItemCopy', $new_item, $item);

					foreach ($result as $result_value)
					{
						if ($result_value === false)
							continue 2;
					}
					
					$new_item->save();
					$items[] = $new_item;
				}

				$parent_order->create_sub_order($items, false, $this->formGetEditSessionKey(), $model);
			}
		}

		/*
		 * Edit order
		 */

		protected function onCopyBillingAddress($order_id)
		{
			try
			{
				$form_model = $this->getOrderObj($order_id);
				$form_model->copy_billing_address(post('Shop_Order'));

				if ($form_model->is_new_record())
				{
					echo ">>tab_4<<";
					$this->formRenderFormTab($form_model, 3);
				} else
				{
					echo ">>tab_3<<";
					$this->formRenderFormTab($form_model, 2);
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onBillingCountryChange()
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_Order');
			$form_model->billing_country_id = $data['billing_country_id'];
			echo ">>form_field_container_billing_state_idShop_Order<<";
			$this->formRenderFieldContainer($form_model, 'billing_state');
		}
		
		protected function onShippingCountryChange() 
		{
			$form_model = $this->formCreateModelObject();
			
			$data = post('Shop_Order');
			$form_model->shipping_country_id = $data['shipping_country_id'];
			echo ">>form_field_container_shipping_state_idShop_Order<<";
			$this->formRenderFieldContainer($form_model, 'shipping_state');
		}

		protected function onLoadItemForm()
		{
			try
			{
				$item = Shop_OrderItem::create()->find(post('item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Item not found');

				if (!$item->product)
					throw new Phpr_ApplicationException('Item product not found');

				Shop_OrderHelper::apply_single_item_discount($item,  post('applied_discounts_data'));
				$item->define_form_fields();
				$this->viewData['item'] = $item;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->viewData['edit_session_key'] = post('edit_session_key');
			$this->viewData['edit_mode'] = true;
			$this->renderPartial('edit_order_item');
		}
		
		protected function onUpdateItemPriceAndDiscount($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);
				$item = Shop_OrderItem::create()->find(post('item_id'));
				if ($item && post('auto_discount_price_eval'))
				{
					if (!post('shop_product_id'))
						$product = $item->product;
					else
					{
						$product = Shop_Product::create()->find(post('shop_product_id'));
						if (!$product)
							return;
					}
					
					$customer = Shop_OrderHelper::find_customer($order);
					$customer_group_id = Shop_OrderHelper::find_customer_group_id($order);

					$item->quantity = post('quantity');
					$product_options = post('product_options', array());
					$product_options = $product->normalize_posted_options($product_options);
					
					$item->product = $product;

					$effective_quantity = $item->quantity;
					if ($product->tier_prices_per_customer && $customer)
						$effective_quantity += $customer->get_purchased_item_quantity($product);

					$item->auto_discount_price_eval = 1;
					
					$bundle_item_product_id = post('bundle_item_product_id');
					if (!$bundle_item_product_id)
					{
						$om_record = Shop_OptionMatrixRecord::find_record($product_options, $product, true);
						if (!$om_record)
							$price = max($product->price_no_tax($effective_quantity, $customer_group_id) - $product->get_discount($effective_quantity, $customer_group_id), 0);
						else
							$price = $om_record->get_sale_price($product, $effective_quantity, $customer_group_id, true);
					}
					else
					{
						$bundle_item_product = Shop_BundleItemProduct::create()->find($bundle_item_product_id);
						if (!$bundle_item_product)
							throw new Phpr_ApplicationException('Bundle item product not found.');

						$price = max($bundle_item_product->get_price_no_tax($product, $effective_quantity, $customer_group_id, $product_options) - $product->get_discount($effective_quantity, $customer_group_id), 0);
					}
					
					$item->price = $price;

					$this->viewData['item'] = $item;

					$this->renderMultiple(array(
						'item_price_and_discount'=>'@_item_price_and_discount',
						'item_description'=>'@_item_description',
						'item_in_stock_indicator'=>'@_item_in_stock_indicator'
					));
				}
			}
			catch (Exception $ex)
			{
			}
		}

		public function prepare_product_list()
		{
			return Shop_Product::create()->where('shop_products.grouped is null')->where('shop_products.disable_completely is null or shop_products.disable_completely=0');
		}

		protected function onAddProduct($order_id)
		{
			try
			{
				$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
				
				if (post('bundle_item_product_id'))
				{
					$item_product = Shop_BundleItemProduct::create()->find(post('bundle_item_product_id'));
					if (!$item_product)
						throw new Phpr_ApplicationException('Bundle item product not found');
						
					$_POST['product_id'] = $item_product->product->id;
				}

				$product = Shop_Product::create()->find(post('product_id'));
				if (!$product)
					throw new Phpr_ApplicationException('Product not found');

				$customer = Shop_OrderHelper::find_customer($order);
				$customer_group_id = Shop_OrderHelper::find_customer_group_id($order);
				
				$grouped_products = $product->grouped_products;
				if ($grouped_products->count)
					$product = $grouped_products->first;

				$item_obj = Shop_OrderItem::create()->init_empty_item($product, $customer_group_id, $customer, post('bundle_item_product_id'));
				$item_obj->save();
				
				$this->viewData['item'] = $item_obj;
				$item_obj->define_form_fields();
				$this->viewData['edit_session_key'] = post('edit_session_key');
				
				$this->viewData['edit_mode'] = false;
				$this->viewData['bundle_item_product_id'] = post('bundle_item_product_id');
				$this->viewData['bundle_master_order_item_id'] = post('bundle_master_order_item_id');
				$bundle_master_bundle_item_id = $this->viewData['bundle_master_bundle_item_id'] = post('bundle_master_bundle_item_id');
				
				if ($bundle_master_bundle_item_id)
				{
					$bundle_item = Shop_ProductBundleItem::create()->find($bundle_master_bundle_item_id);
					if ($bundle_item)
						$this->viewData['bundle_master_bundle_item_name'] = $bundle_item->name;
				}
				
				$this->renderPartial('edit_order_item');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onAddItem($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);

				$item = Shop_OrderItem::create()->find(post('item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Item not found');

				if (!$item->product)
					throw new Phpr_ApplicationException('Item product not found');

				$item->disable_column_cache();
				$item->set_from_post($this->formGetEditSessionKey());

				$items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
				$same_item = $item->find_same_item($items, $this->formGetEditSessionKey());
				if ($same_item)
				{
					$same_item->quantity += $item->quantity;

					if ($same_item->auto_discount_price_eval)
					{
						$customer_group_id = Shop_OrderHelper::find_customer_group_id($order);
						$customer = Shop_OrderHelper::find_customer($order);
						
						$effective_quantity = $same_item->quantity;
						if ($item->product->tier_prices_per_customer && $customer)
							$effective_quantity += $customer->get_purchased_item_quantity($item->product);

						$same_item->price = round($same_item->product->price_no_tax($effective_quantity, $customer_group_id), 2);
						$same_item->discount = round($same_item->product->get_discount($effective_quantity, $customer_group_id), 2);
					}

					$same_item->save(null, $this->formGetEditSessionKey());
					$item->delete();
				} else
					$order->items->add($item, post('edit_session_key'));
					
				echo ">>data_placeholder<<";
				echo "no_data";
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}


		
		protected function onUpdateItem($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);

				$item = Shop_OrderItem::create()->find(post('item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Item not found');

				if (!$item->product)
					throw new Phpr_ApplicationException('Item product not found');

				$item->disable_column_cache();
				$item->set_from_post($this->formGetEditSessionKey());
				$item_id = $item->id;
				
				$items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());

				$same_item = $item->find_same_item($items, $this->formGetEditSessionKey());

				if ($same_item)
				{
					$same_item->quantity += $item->quantity;
					
					if ($same_item->auto_discount_price_eval)
					{
						$customer_group_id = Shop_OrderHelper::find_customer_group_id($order);
						$customer = Shop_OrderHelper::find_customer($order);
						
						$effective_quantity = $same_item->quantity;
						if ($item->product->tier_prices_per_customer && $customer)
							$effective_quantity += $customer->get_purchased_item_quantity($item->product);

						$same_item->price = round($same_item->product->price_no_tax($effective_quantity, $customer_group_id), 2);
						$same_item->discount = round($same_item->product->get_discount($effective_quantity, $customer_group_id), 2);
					}

					$same_item->save(null, $this->formGetEditSessionKey());
					$item_id = $same_item->id;

					$item->delete();
				}

				// $order->items->add($item, post('edit_session_key'));
				
				$item->update_bundle_item_quantities($items);
				
				$this->remove_cart_discount($item_id);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onUpdateItemList($order_id)
		{
			try
			{
				$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
				$order->set_form_data(post('Shop_Order'));

				echo ">>item_list<<";
				$this->renderPartial('item_list');

				$this->renderOrderTotals($order);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onDeleteItem($order_id)
		{
			try
			{
				$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);

				$item = Shop_OrderItem::create()->find(post('item_id'));
				if ($item)
				{
					$order->items->delete($item, $this->formGetEditSessionKey());

					$items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
					foreach ($items as $sub_item)
					{
						if ($sub_item->bundle_master_order_item_id == $item->id)
							$order->items->delete($sub_item, $this->formGetEditSessionKey());
					}
				}

				$order->set_form_data(post('Shop_Order'));

				echo ">>item_list<<";
				$this->renderPartial('item_list');
				
				$this->renderOrderTotals($order);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onUpdateProductId($order_id)
		{
			try
			{
				$item = Shop_OrderItem::create()->find(post('item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Item not found');

				$item->product = Shop_Product::create()->find(post('shop_product_id'));
				if (!$item->product)
					throw new Phpr_ApplicationException('Product not found');
					
				$order = $this->getOrderObj($order_id);

				$customer_group_id = Shop_OrderHelper::find_customer_group_id($order);
				$customer = Shop_OrderHelper::find_customer($order);
				$product_options = post('product_options', array());
				$product_options = $item->product->normalize_posted_options($product_options);

				$effective_quantity = $item->quantity;
				if ($item->product->tier_prices_per_customer && $customer)
					$effective_quantity += $customer->get_purchased_item_quantity($item->product);
					
				$bundle_item_product_id = post('bundle_item_product_id');
				if (!$bundle_item_product_id)
				{
					$om_record = Shop_OptionMatrixRecord::find_record($product_options, $item->product, true);
					if (!$om_record)
						$price = max(round($item->product->price_no_tax($effective_quantity, $customer_group_id), 2) - $item->product->get_discount($effective_quantity, $customer_group_id), 0);
					else
						$price = $om_record->get_sale_price($item->product, $effective_quantity, $customer_group_id, true);
				}
				else {
					$bundle_item_product = Shop_BundleItemProduct::create()->find($bundle_item_product_id);
					if (!$bundle_item_product)
						throw new Phpr_ApplicationException('Bundle item product not found.');

					$price = max(round($bundle_item_product->get_price_no_tax($item->product, $effective_quantity, $customer_group_id, $product_options), 2) - $item->product->get_discount($effective_quantity, $customer_group_id), 0);
				}

				$item->price = $price;
				$item->discount = 0;
				
				$item->define_form_fields();
				
				$item_data = post('Shop_OrderItem', array());
				foreach ($item_data as $key=>$value)
				{
					$column = $item->find_column_definition($key);
					if ($column && $column->type == db_date)
					{
						$value = trim($value);
						if (strlen($value)) 
						{
							$item->$key = Phpr_DateTime::parse($value, '%x');
							if (!$item->$key)
								throw new Phpr_ApplicationException(sprintf('Invalid value in the %s field.', $column->displayName));
						}
					} else
						$item->$key = $value;
				}
				
				$this->viewData['item'] = $item;

				$this->renderPartial('item_form');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onLoadFindProductForm()
		{
			try
			{
				$this->viewData['edit_session_key'] = post('edit_session_key');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('find_product_form');
		}
		
		protected function onUpdateBundleProductList()
		{
			try
			{
				$item = Shop_ProductBundleItem::create()->find(post('bundle_item_id'));
				if (!$item)
					throw new Phpr_ApplicationException('Bundle item not found.');

				$this->viewData['products'] = $item->item_products;
				$this->renderPartial('bundle_item_products');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadFindBundleProductForm()
		{
			try
			{
				$parent_item = Shop_OrderItem::create()->find(post('bundle_parent'));
				if (!$parent_item)
					throw new Phpr_ApplicationException('Parent order item not found.');
					
				if (!$parent_item->product->bundle_items->count)
					throw new Phpr_ApplicationException('Selected product has no bundle items.');
				
				$this->viewData['edit_session_key'] = post('edit_session_key');
				$this->viewData['bundle_items'] = $parent_item->product->bundle_items;
				$this->viewData['bundle_master_order_item_id'] = post('bundle_parent');
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('find_bundle_product_form');
		}

		protected function onUnlockOrder($order_id){
			try {
				$order = $this->getOrderObj( $order_id );
				if ( !$this->currentUser->get_permission( 'shop', 'lock_orders' ) ) {
					throw new Phpr_ApplicationException( 'You do not have permission to unlock orders' );
				}
				$order->unlock_order();
				$order->save();
				Phpr::$response->redirect( Phpr::$request->getReferer( post( 'url' ) ) );
			}
			catch (Exception $ex) {
				$this->handlePageError($ex);
			}
		}

		protected function onLockOrder($order_id){
			try {
				$order = $this->getOrderObj( $order_id );
				if ( !$this->currentUser->get_permission( 'shop', 'lock_orders' ) ) {
					throw new Phpr_ApplicationException( 'You do not have permission to lock orders' );
				}
				$order->lock_order();
				$order->save();
				Phpr::$response->redirect( Phpr::$request->getReferer( post( 'url' ) ) );
			}
			catch (Exception $ex) {
				$this->handlePageError($ex);
			}
		}

		protected function onCopyOrder($order_id){
			try {
				$order = $this->getOrderObj( $order_id );
				$session_key = $this->formGetEditSessionKey();
				$order_copy = $order->create_order_copy();
				Phpr::$response->redirect( url('/shop/orders/edit/'.$order_copy->id) );
			}
			catch (Exception $ex) {
				$this->handlePageError($ex);
			}
		}


		public function formBeforeSave($order, $session_key)
		{
			$orderData = post('Shop_Order');
			$deferred_session_key = $this->formGetEditSessionKey();

			$order->set_shipping_address($orderData);
			$order->coupon_id = array_key_exists('coupon_id', $orderData) ? $orderData['coupon_id'] : null;
			$order->shipping_method_id = array_key_exists('shipping_method_id', $orderData) ? $orderData['shipping_method_id'] : null;
			$order->payment_method_id = array_key_exists('payment_method_id', $orderData) ? $orderData['payment_method_id'] : null;
			$order->billing_country_id = array_key_exists('billing_country_id', $orderData) ? $orderData['billing_country_id'] : null;
			$order->override_shipping_quote = array_key_exists('override_shipping_quote', $orderData) ? $orderData['override_shipping_quote'] : null;

			/*
			 * Validate shipping parameters
			 */

			$shipping_method_id = $order->shipping_method_id;
			if (strpos($shipping_method_id, '_') !== false)
			{
				$parts = explode('_', $order->shipping_method_id);
				$order->shipping_sub_option_id = $shipping_method_id;
				$order->shipping_method_id = $parts[0];
			}
			
			if ($order->override_shipping_quote)
			{
				$manual_shipping_quote = trim(post_array_item('Shop_Order', 'manual_shipping_quote'));
				
				if (!Core_Number::is_valid($manual_shipping_quote))
					throw new Phpr_ApplicationException('Please enter a valid shipping quote or disable the "Override shipping quote" option');

			} else {
				$shipping_methods = $order->list_available_shipping_options($deferred_session_key, false);

				$shipping_method_found = false;
				foreach ($shipping_methods as $method)
				{
					if ($method->id == $order->shipping_method_id)
					{
						$shipping_method_found = true;
						break;
					}
				}

				if (!$shipping_method_found)
					throw new Phpr_ApplicationException('Please select shipping method');
			}

			/*
			 * Validate payment method
			 */
			$payment_methods = $this->getAvailablePaymentMethods($order);

			$payment_method_found = false;
			foreach ($payment_methods as $method)
			{
				if ($method->id == $order->payment_method_id)
				{
					$payment_method_found = true;
					break;
				}
			}
			
			if (!$payment_method_found)
				throw new Phpr_ApplicationException('Please select payment method');

			$form_data = post('Shop_Order', array());
			$items = $this->evalOrderTotals($order,null);

			/*
			 * Update items tax value
			 */

			$save = true;
			Shop_OrderHelper::apply_item_discounts($items, post('applied_discounts_data'), $save);

		}
		
		public function formAfterSave($model, $session_key)
		{
			try
			{
				$applied_rules = post('order_applied_discount_list');
				if ($applied_rules)
				{
					$applied_rules = unserialize($applied_rules);
					$model->set_applied_cart_rules($applied_rules);
				}
				
				if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer')
					$this->form_create_save_redirect = url('/shop/customers/preview/'.$model->customer_id);

			} catch (exception $ex) {}
		}

		protected function onUpdateShippingOptions($order_id)
		{
			$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
			$orderData = post('Shop_Order');
			if ($order->is_new_record()){
				$order->set_form_data($orderData);
			}

			$order->set_shipping_address($orderData);
			$order->coupon_id = array_key_exists('coupon_id', $orderData) ? $orderData['coupon_id'] : null;
			$order->override_shipping_quote = array_key_exists('override_shipping_quote', $orderData) ? $orderData['override_shipping_quote'] : null;
			$order->manual_shipping_quote = array_key_exists('manual_shipping_quote', $orderData) ? $orderData['manual_shipping_quote'] : null;
			$order->shipping_method_id = array_key_exists('shipping_method_id', $orderData) ? $orderData['shipping_method_id'] : null;
			
			if (strpos($order->shipping_method_id, '_') !== false)
			{
				$parts = explode('_', $order->shipping_method_id);
				$order->shipping_sub_option_id = $order->shipping_method_id;
				$order->shipping_method_id = $parts[0];
			}

			if ($order->is_new_record())
			{
				echo ">>tab_5<<";
				$this->formRenderFormTab($order, 4);
			} else
			{
				echo ">>tab_4<<";
				$this->formRenderFormTab($order, 3);
			}
		}
		
		protected function onUpdateBillingOptions($order_id)
		{
			$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
			$orderData = post('Shop_Order');
			$order->billing_country_id = $orderData['billing_country_id'];
			$order->payment_method_id = array_key_exists('payment_method_id', $orderData) ? $orderData['payment_method_id'] : null;

			if ($order->is_new_record())
			{
				echo ">>tab_6<<";
				$this->formRenderFormTab($order, 5);
			} else
			{
				echo ">>tab_5<<";
				$this->formRenderFormTab($order, 4);
			}
		}
		
		private function getOrderObj($id)
		{
			return (strlen($id) && $id != 'invoice' && $id != 'for-customer') ? $this->formFindModelObject($id) : $this->formCreateModelObject();
		}


		private function evalOrderTotals($order, $items = null)
		{
			return Shop_OrderHelper::evalOrderTotals($order,$items,$this->formGetEditSessionKey(),post('applied_discounts_data', false));
		}
		
		private function renderOrderTotals($order, $items = null)
		{
			echo ">>order_totals<<";
			$this->evalOrderTotals($order,$items);
			$this->viewData['form_model'] = $order;

			$this->renderPartial('order_totals');
		}
		
		protected function onUpdateTotals($order_id)
		{
			$order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
			$order->set_form_data(post('Shop_Order', array()));

			Shop_TaxClass::set_tax_exempt($order->tax_exempt);
			Shop_TaxClass::set_customer_context(Shop_OrderHelper::find_customer($order, true));
			
			$this->renderOrderTotals($order);
		}

		protected function edit_onDeleteOrder($order_id, $redirect_url = null)
		{
			try
			{
				$order = $this->getOrderObj($order_id);
				$order->cancelDeferredBindings($this->formGetEditSessionKey());
				$order->delete_order();
				Phpr::$session->flash['success'] = 'Order has been marked as deleted.';

				$redirect_url = $redirect_url ? $redirect_url : url('/shop/orders');
				
				Phpr::$response->redirect($redirect_url);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function edit_onDeleteOrderPermanently($order_id)
		{
			if ( !$this->currentUser->get_permission( 'shop', 'delete_orders' ) ) {
				throw new Phpr_ApplicationException( 'You do not have permission to permanently delete orders' );
			}
			try
			{
				$order = $this->getOrderObj($order_id);
				$order->cancelDeferredBindings($this->formGetEditSessionKey());
				$order->delete();
				Phpr::$session->flash['success'] = 'Order has been successfully deleted.';
				
				Phpr::$response->redirect(url('/shop/orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onCalculateDiscounts($order_id )
		{
			$cart_items = null;
			$items = null;
			$discount_info = null;

			try
			{
				$deferred_session_key = $this->formGetEditSessionKey();
				$orderData = post('Shop_Order');

				$order = $this->getOrderObj($order_id);
				$order->set_form_data($orderData);

				$order->validate_data($orderData, $deferred_session_key);

				$results = Shop_OrderHelper::recalc_order_discounts($order, $deferred_session_key);
				extract($results);

				echo ">>form_field_container_free_shippingShop_Order<<";
				$this->formRenderFieldContainer($order, 'free_shipping');
				
				$this->update_cart_discounts($cart_items);
				$this->renderOrderTotals($order, $items);
				
				echo ">>order_applied_discount_list<<";
				$this->renderPartial('applied_discounts_list', array('order_applied_discount_list'=>$discount_info->applied_rules));

				echo ">>item_list<<";
				$this->renderPartial('item_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function onLoadDiscountForm($order_id)
		{
			try
			{
				$orderData = post('Shop_Order');

				$order = $this->getOrderObj($order_id);
				$order->set_form_data($orderData);
				$order->validate_data($orderData, $this->formGetEditSessionKey());

				$subtotal = 0;
				$items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
				foreach ($items as $item)
					$subtotal += $item->single_price*$item->quantity;

				$this->viewData['order'] = $order;
				$this->viewData['subtotal'] = $subtotal;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->viewData['edit_session_key'] = post('edit_session_key');
			$this->renderPartial('order_discount_form');
		}
		
		protected function onApplyDiscount($order_id)
		{
			try
			{
				/*
				 * Load the order and calculate the subtotal
				 */

				$orderData = post('Shop_Order');
				$order = $this->getOrderObj($order_id);
				$order->set_form_data($orderData);
				$order->validate_data($orderData, $this->formGetEditSessionKey());

				$subtotal = 0;
				$items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
				foreach ($items as $item)
					$subtotal += $item->single_price*$item->quantity;

				/*
				 * Validate the specified discount value
				 */

				$value = trim(post('discount_value'));
				if (!strlen($value))
					throw new Phpr_ApplicationException('Please enter a discount value');

				if (!preg_match('/^([0-9]+\.[0-9]+%|[0-9]+%?|[0-9]+\.[0-9]+%?)$/', $value))
					throw new Phpr_ApplicationException('Invalid discount value. Please specify a number or percentage value.');

				if ($value < 0)
					throw new Phpr_ApplicationException('Discount value cannot be negative.');

				$is_percentage = substr($value, -1) == '%';
				if ($is_percentage)
				{
					$value = substr($value, 0, -1);
					if ($value > 100)
						throw new Phpr_ApplicationException('The discount value cannot exceed 100%.');
						
					$value = $subtotal*$value/100;
				} else
				{
					if ($value > $subtotal)
						throw new Phpr_ApplicationException('The discount value cannot exceed the order subtotal ('.$order->format_currency($subtotal).').');
				}

				/*
				 * Prepare the cart items and execute the fixed cart discount action
				 */

				$cart_items = Shop_OrderHelper::items_to_cart_items_array($items);
				foreach ($cart_items as $cart_item) {
					$cart_item->applied_discount = 0;
					$cart_item->ignore_product_discount = true;
				}

				$item_discount_map = array();
				foreach ($cart_items as $cart_item)
				{
					$item_discount_map[$cart_item->key] = 0;
					$item_discount_tax_incl_map[$cart_item->key] = 0;
				}
					
				$discount_action = new Shop_CartFixed_Action();

				$params = array('cart_items'=>$cart_items, 'no_tax_include'=>true);
				$action_params = array('discount_amount'=>$value);
				$discount = $discount_action->eval_discount($params, (object)$action_params, $item_discount_map, $item_discount_tax_incl_map, null);
				
				foreach ($item_discount_map as $key=>&$value)
					$value = max(0, $value);

				/**
				 * Apply discounts to cart items
				 */

				$total_discount = 0;
				foreach ($cart_items as $cart_item)
				{
					$cart_item->applied_discount = $item_discount_map[$cart_item->key];
					$cart_item->order_item->discount = $cart_item->total_discount_no_tax();
					$total_discount += $cart_item->total_discount_no_tax()*$cart_item->quantity;
				}

				$order->discount = $total_discount;

				$this->update_cart_discounts($cart_items);
				$this->renderOrderTotals($order, $items);

				$order->coupon = null;
				echo ">>form_field_container_coupon_idShop_Order<<";
				$this->formRenderFieldContainer($order, 'coupon');
				
				echo ">>order_applied_discount_list<<";
				$this->renderPartial('applied_discounts_list', array('order_applied_discount_list'=>array()));

				echo ">>item_list<<";
				$this->renderPartial('item_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function update_cart_discounts($items)
		{
			$discounts = array();
			foreach ($items as $item)
				$discounts[$item->order_item->id] = $item->total_discount_no_tax();

			echo ">>order_applied_discounts_data<<";
			$this->renderPartial('applied_discounts_data', array('applied_discount_data'=>$discounts));
			$_POST['applied_discounts_data'] = serialize($discounts);
		}
		
		protected function remove_cart_discount($item_id)
		{
			$data = post('applied_discounts_data');
			if (strlen($data))
			{
				try
				{
					$data = unserialize($data);
					if (array_key_exists($item_id, $data))
						unset($data[$item_id]);
						
					$_POST['applied_discounts_data'] = serialize($data);

					echo ">>order_applied_discounts_data<<";
					$this->renderPartial('applied_discounts_data', array('applied_discount_data'=>$data));
				}
				catch (Exception $ex) {}
			}
			
			echo ">>data_placeholder<<";
			echo "no_data";
		}


		protected function apply_item_discounts(&$items)
		{
			Shop_OrderHelper::apply_item_discounts($items, post('applied_discounts_data'));
		}


		protected function apply_single_item_discount($item)
		{
			Shop_OrderHelper::apply_item_discounts($item, post('applied_discounts_data'));
		}
		
		/*
		 * Payment page
		 */
		
		public function pay($order_id)
		{
			try
			{
				$this->app_page_title = 'Pay';
				$this->viewData['form_record_id'] = $order_id;
				$this->viewData['order'] = $order = $this->formFindModelObject($order_id);
				
				$payment_method = $order->payment_method();
				if (!$payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');
					
				$payment_method->order = $order;
					
				$payment_method->define_form_fields('backend_payment_form');
				$this->viewData['payment_method'] = $payment_method;
				$this->viewData['payment_method_obj'] = $payment_method->get_paymenttype_object();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		public function pay_onSubmit($order_id)
		{
			try
			{
				$order = $this->formFindModelObject($order_id);
				$payment_method = $order->payment_method();
				if (!$payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$form_data = post('Shop_PaymentMethod', array());
				$payment_method->define_form_fields('backend_payment_form');
				
				$pay_from_profile = post('pay_from_profile');
				if (!$pay_from_profile)
					$payment_method->validate_data($form_data);
				
				$payment_method->define_form_fields();
				$payment_method_obj = $payment_method->get_paymenttype_object();
				
				if (!$pay_from_profile)
					$payment_method_obj->process_payment_form($form_data, $payment_method, $order, true);
				else
					$payment_method_obj->pay_from_profile($payment_method, $order, true);

				Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function pay_onPayFromProfile($order_id)
		{
			try
			{
				$order = $this->formFindModelObject($order_id);
				$payment_method = $order->payment_method();
				if (!$payment_method)
					throw new Phpr_ApplicationException('Payment method not found.');

				$form_data = post('Shop_PaymentMethod', array());
				$payment_method->define_form_fields('backend_payment_form');
				
				$payment_method->define_form_fields();
				$payment_method_obj = $payment_method->get_paymenttype_object();

				$payment_method_obj->pay_from_profile($payment_method, $order, true);

				Phpr::$response->redirect(url('/shop/orders/payment_accepted/'.$order->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function payment_accepted($order_id)
		{
			try
			{
				$this->app_page_title = 'Payment Accepted';
				$this->viewData['form_record_id'] = $order_id;
				$this->viewData['order'] = $order = $this->formFindModelObject($order_id);
				
//				Phpr::$response->redirect(url('/shop/orders/preview/'.$order->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		public function rss()
		{
			$this->suppressView();
			
			$user = Phpr::$security->http_authentication('LemonStand Orders Rss', 'You must enter a valid login name and password to access the RSS channel.');
			
			Backend::$events->fireEvent('shop:onBeforeOrdersRssExport');
			
			if (!$user->get_permission('shop', 'manage_orders_and_customers'))
				echo "You have no rights to access the orders RSS channel";
			else
				echo Shop_Order::get_rss(20);
		}
		
		/*
		 * Shipping labels and tracking codes
		 */
		
		protected function preview_onLoadShippingLabelForm($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);

				$shipping_method = $order->shipping_method;
				if (!$shipping_method)
					throw new Phpr_ApplicationException('Shipping method not found.');
					
				$shipping_method->order = $order;

				$shipping_method->define_form_fields('print_label');
				$this->viewData['shipping_method'] = $shipping_method;
				$this->viewData['shipping_method_obj'] = $shipping_method->get_shippingtype_object();
				$this->viewData['tracking_code'] = Shop_OrderTrackingCode::find_by_order_and_method($order, $order->shipping_method);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('print_label_form');
		}
		
		protected function preview_onGenerateLabels($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);

				$shipping_method = $order->shipping_method;
				if (!$shipping_method)
					throw new Phpr_ApplicationException('Shipping method not found.');
					
				$shipping_method->order = $order;
				$labels = $shipping_method->generate_shipping_labels($order, post('Shop_ShippingOption', array()));
				$this->viewData['labels'] = $labels;
				$this->viewData['form_model'] = $order;
				
				$this->renderMultiple(array(
					'shipping_label_list'=>'@_shipping_label_links',
					'tracking_code_list'=>'@_tracking_code_list'
				));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function shippinglabel($link)
		{
			try
			{
				Shop_ShippingLabel::output_label($link);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function preview_onDeleteTrackingCode($order_id)
		{
			try
			{
				$code = Shop_OrderTrackingCode::create()->find(post('code_id'));
				if (!$code)
					throw new Phpr_ApplicationException('Tracking code not found.');
					
				Backend::$events->fireEvent('shop:onBeforeDeleteShippingTrackingCode', $order_id, $code);

				$code->delete();

				$this->viewData['form_model'] = $this->getOrderObj($order_id);
				$this->renderPartial('tracking_code_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onLoadShippingCodeForm($order_id)
		{
			try
			{
				$order = $this->getOrderObj($order_id);

				$shipping_method = $order->shipping_method;
				if (!$shipping_method)
					throw new Phpr_ApplicationException('Shipping method not found.');

				$model = new Shop_OrderTrackingCode();
				$model->shipping_method_id = $order->shipping_method_id;

				$model->init_columns_info();
				$model->define_form_fields();

				$this->viewData['tracking_code'] = $model;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('tracking_code_form');
		}
		
		protected function preview_onSaveTrackingNumber($order_id)
		{
			try
			{
				$model = new Shop_OrderTrackingCode();
				$model->init_columns_info();
				$model->define_form_fields();

				$order = $this->getOrderObj($order_id);

				$model->order_id = $order_id;
				$model->save(post('Shop_OrderTrackingCode', array()));

				$this->viewData['form_model'] = $order;
				$this->renderPartial('tracking_code_list');
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
			$order = null;
			
			if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create' || Phpr::$router->action == 'preview')
				$order = $this->getOrderObj($id);

			Backend::$events->fireEvent(post('custom_event_handler'), $this, $order);
		}


		/*
		 * Order Helper Functions
		 */

		protected function getAvailablePaymentMethods($order)
		{
			$data = post('Shop_Order', array());
			if ($data)
				$order->set_form_data($data);

			return Shop_OrderHelper::getAvailablePaymentMethods($order,$this->formGetEditSessionKey());
		}

		/**
		 * @deprecated since v1.3
		 */
		protected function getAvailableShippingMethods($order)
		{
			return $order->list_available_shipping_options($this->formGetEditSessionKey(), false);
		}


		/**
		 * @deprecated since v1.3
		 */
		protected function findLastOrder()
		{
			return Shop_OrderHelper::findLastOrder();
		}

		/**
		 * @deprecated since v1.3
		 */
		private function find_customer($order, $check_order_data = false)
		{
			return Shop_OrderHelper::find_customer($order,$check_order_data);
		}

		/**
		 * @deprecated since v1.3
		 */
		private function find_customer_group_id($order)
		{
			$customer = Shop_OrderHelper::find_customer($order);

			if ($customer)
				return $customer->customer_group_id;
			else
				return Shop_CustomerGroup::get_guest_group()->id;
		}

	}
?>