<?

	class Shop_Customers extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Db_FilterBehavior';
		public $list_model_class = 'Shop_Customer';
		public $list_record_url = null;
		public $list_options = array();
		public $list_custom_prepare_func = null;
		public $list_name = null;
		public $list_top_partial = 'customer_selectors';

		public $form_preview_title = 'Customer';
		public $form_create_title = 'New Customer';
		public $form_edit_title = 'Edit Customer';
		public $form_model_class = 'Shop_Customer';
		public $form_not_found_message = 'Customer not found';
		public $form_redirect = null;
		public $form_edit_save_redirect = null;
		public $form_delete_redirect = null;
		public $enable_concurrency_locking = true;
		public $form_no_flash = false;

		public $form_edit_save_flash = 'The customer has been successfully saved';
		public $form_create_save_flash = 'The customer has been successfully added';
		public $form_edit_delete_flash = 'The customer has been successfully deleted';

		public $list_search_enabled = true;
		public $list_search_fields = array('concat(@first_name, " ", @last_name)', '@first_name', '@last_name', '@company', '@email', '@shipping_street_addr', '@billing_street_addr', '@billing_city', '@shipping_city', 'billing_country_calculated_join.name', 'shipping_country_calculated_join.name', 'billing_state_calculated_join.name', 'shipping_state_calculated_join.name');
		public $list_search_prompt = 'find customers by name, company or email';

		public $globalHandlers = array('onUpdateStatesList', 'edit_onDelete');
		public $filter_onApply = 'listReload();';
		public $filter_onRemove = 'listReload();';
		
		public $list_render_filters = false;
		public $filter_list_title = 'Filter customers';
		
		public $csv_import_file_columns_header = 'File Columns';
		public $csv_import_db_columns_header = 'LemonStand Customer Columns';
		public $csv_import_data_model_class = 'Shop_Customer';
		public $csv_import_config_model_class = 'Shop_CustomerCsvImportModel';
		public $csv_import_name = 'Customer import';
		public $csv_import_url = null;
		public $csv_import_short_name = 'Customers';

		
		public $filter_filters = array(
			'billing_country'=>array('name'=>'Billing Country', 'class_name'=>'Shop_CustomerBillingCountryFilter', 'prompt'=>'Please choose billing countries you want to include to the list. Customers with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'billing_state'=>array('name'=>'Billing State', 'class_name'=>'Shop_CustomerBillingStateFilter', 'prompt'=>'Please choose billing states you want to include to the list. Customers with other billing states will be hidden.', 'added_list_title'=>'Added States'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'customer_deleted_status'=>array('name'=>'Status', 'class_name'=>'Shop_DeletedFilter', 'prompt'=>'Please choose whether you want to see only deleted or only active customers.', 'added_list_title'=>'Added Statuses'),
		);
		
		protected $required_permissions = array('shop:manage_orders_and_customers');

		public function __construct()
		{
			if (Phpr::$router->action == 'import_csv' || post('import_csv_flag') || Phpr::$router->action == 'import_csv_get_config')
				$this->implement .= ', Backend_CsvImport';
				
			if (Phpr::$router->action == 'import_csv')
				$this->required_permissions = array('shop:customers_export_import');
			
			Backend::$events->fireEvent('shop:onConfigureCustomersPage', $this);

			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'customers';
			$this->app_module_name = 'Shop';

			$this->csv_import_url = url('/shop/customers');

			$this->list_record_url = url('/shop/customers/preview/');
			$this->form_edit_save_redirect = url('/shop/customers/preview/%s?'.uniqid());
			$this->form_redirect = url('/shop/customers');
			$this->form_delete_redirect = url('/shop/customers');
			
			if (post('filter_request'))
				$this->list_top_partial = null;
				
			Backend::$events->fireEvent('shop:onDisplayCustomersPage', $this);
		}
		
		public function index()
		{
			$this->app_page_title = 'Customers';
		}
		
		protected function index_onLoadMergeCustomersForm()
		{
			try
			{
				$customer_ids = post('list_ids', array());
				
				if (count($customer_ids) < 2)
					throw new Phpr_ApplicationException('Please select at least 2 customers to merge.');
				
				$model = new Shop_MergeCustomersModel();
				$model->init($customer_ids);
				$this->viewData['model'] = $model;
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('merge_customers_form');
		}
		
		protected function index_onMergeSelectedCustomers()
		{
			try
			{
				$model = new Shop_MergeCustomersModel();
				$model->apply(post(get_class($model)), array());

				Phpr::$session->flash['success'] = 'The customers have been successfully merged.';
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}

			$this->renderPartial('customers_page_content');
		}
		
		public function export_customers($format = null)
		{
			$this->list_name = 'Shop_Customers_index_list';
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('customers.csv', $options);
		}
		
		public function listPrepareData()
		{
			$obj = Shop_Customer::create();
			$this->filterApplyToModel($obj);

			return $obj;
		}
		
		public function listGetRowClass($model)
		{
			return $model->deleted_at ? 'deleted' : 'customer_active';
		}
		
		protected function preview_onRestoreCustomer($customer_id)
		{
			try
			{
				$obj = $this->formFindModelObject($customer_id);
				$obj->restore_customer();

				Phpr::$session->flash['success'] = 'The customer has been restored.';
				Phpr::$response->redirect(url('/shop/customers/preview/'.$obj->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function preview_onShowConvertForm($customer_id)
		{
			try
			{
				$obj = $this->formFindModelObject($customer_id);
				if (!$obj)
					throw new Phpr_ApplicationException('Customer not found');
					
				if (Shop_Customer::find_registered_by_email($obj->email))
					throw new Phpr_ApplicationException("Registered customer with email {$obj->email} already exists.");
					
				$this->viewData['groups'] = Shop_CustomerGroup::create()->order('name')->where('(code is null or code <> ?)', Shop_CustomerGroup::guest_group)->find_all();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('convert_customer_form');
		}
		
		public function preview_formBeforeRender($order)
		{
			$this->form_no_flash = true;
		}
		
		protected function preview_onConvert($customer_id)
		{
			try
			{
				$obj = $this->formFindModelObject($customer_id);
				$obj->convert_to_registered(post('send_registration_notification'), post('customer_group'));

				Phpr::$session->flash['success'] = 'The customer has been converted to registered.';
				Phpr::$response->redirect(url('/shop/customers/preview/'.$obj->id.'?'.uniqid()));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function evalCustomerNum()
		{
			return Shop_Customer::create()->requestRowCount();
		}
		
		protected function evalGuestCustomerNum()
		{
			return Shop_Customer::create()->where('shop_customers.guest is not null and shop_customers.guest=1')->requestRowCount();
		}
		
		protected function evalRegisteredCustomerNum()
		{
			return Shop_Customer::create()->where('(shop_customers.guest is null or shop_customers.guest=0)')->requestRowCount();
		}
		
		protected function index_onResetFilters()
		{
			$this->filterReset();
			$this->listCancelSearch();
			Phpr::$response->redirect(url('shop/customers'));
		}
		
		protected function index_onDeleteSelected()
		{
			$customers_processed = 0;

			$customers_deleted = 0;
			$customers_marked = 0;

			$customer_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $customer_ids;

			foreach ($customer_ids as $customer_id)
			{
				$customer = null;
				try
				{
					$customer = Shop_Customer::create()->find($customer_id);
					if (!$customer)
						throw new Phpr_ApplicationException('Customer with identifier '.$customer_id.' not found.');
						
					if ($customer->delete_customer())
						$customers_deleted++;
					else
						$customers_marked++;

					$customers_processed++;
				}
				catch (Exception $ex)
				{
					if (!$customer)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting customer "'.$customer->get_display_name().'": '.$ex->getMessage();

					break;
				}
			}

			if ($customers_processed)
			{
				$message = null;
				
				if ($customers_deleted && $customers_marked)
					$message = 'Customers deleted: '.$customers_deleted.', marked as deleted: '.$customers_marked;
				elseif ($customers_deleted)
					$message = 'Customers deleted: '.$customers_deleted;
				elseif ($customers_marked)
					$message = 'Customers marked as deleted: '.$customers_marked;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('customers_page_content');
		}
		
		protected function index_onRestoreSelected()
		{
			$customers_processed = 0;
			$customer_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $customer_ids;

			foreach ($customer_ids as $customer_id)
			{
				$customer = null;
				try
				{
					$customer = Shop_Customer::create()->find($customer_id);
					if (!$customer)
						throw new Phpr_ApplicationException('Customer with identifier '.$customer_id.' not found.');

					if (!$customer->deleted_at)
						continue;

					$customer->restore_customer();

					$customers_processed++;
				}
				catch (Exception $ex)
				{
					if (!$customer)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error restoring customer "'.$customer->get_display_name().'": '.$ex->getMessage();

					break;
				}
			}

			if ($customers_processed)
			{
				if ($customers_processed > 1)
					Phpr::$session->flash['success'] = $customers_processed.' customers have been successfully restored.';
				else
					Phpr::$session->flash['success'] = '1 customer has been successfully restored.';
			}

			$this->renderPartial('customers_page_content');
		}

		protected function onUpdateStatesList()
		{
			$data = post('Shop_Customer');
			$type = post('type');
			
			$this->viewData['states'] = Shop_Customer::create()->list_states($data[$type]);
			$this->viewData['type'] = $type;
			$this->renderPartial('state_list');
		}
		
		public function edit_onDelete($recordId)
		{
			try
			{
				$obj = $this->formFindModelObject($recordId);
				if ($this->enable_concurrency_locking && ($lock = Db_RecordLock::lock_exists($obj)))
					throw new Phpr_ApplicationException(sprintf('User %s is editing this record. The edit session started %s. The record cannot be deleted.', $lock->created_user_name, $lock->get_age_str()));

				if ($deleted = $obj->delete_customer())
					$obj->cancelDeferredBindings($this->formGetEditSessionKey());

				if ($this->enable_concurrency_locking && !Db_RecordLock::lock_exists($obj))
					Db_RecordLock::unlock_record($obj);

				if ($deleted)
				{
					Phpr::$session->flash['success'] = 'The customer has been successfully deleted';
					Phpr::$response->redirect(url('/shop/customers/'));
				} else {
					Phpr::$session->flash['success'] = 'The customer has been marked as deleted';
					Phpr::$response->redirect(url('/shop/customers/preview/'.$recordId.'?'.uniqid()));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Import products
		 */
		
		public function import_csv()
		{
			$this->app_page_title = 'Import Customers';
		}
	}

?>