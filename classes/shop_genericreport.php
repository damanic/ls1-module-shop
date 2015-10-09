<?

	/**
	 * @has_documentable_methods
	 */
	abstract class Shop_GenericReport extends Backend_ChartController
	{
		public $list_record_url = null;
		public $list_columns = array();
		public $list_sorting_column = null;
		
		public $filter_filters = array();
		public $filter_switchers = array();
		
		protected $processed_customer_ids = array();
		protected $required_permissions = array('shop:access_reports');

		public function __construct()
		{
			$user = Phpr::$security->getUser();
			if ($user && $user->get_permission('shop', 'manage_orders_and_customers'))
				$this->list_record_url = url('/shop/orders/preview/%s/').mb_strtolower(get_class($this));

			$this->list_control_panel_partial = PATH_APP.'/modules/shop/controllers/partials/_reports_export_buttons.htm';
			
			Backend::$events->fireEvent('shop:onExtendReportFilters', $this);

			parent::__construct();
		}
		
		public function refererUrl()
		{
			return url('shop/'.preg_replace('/^shop_/', '', mb_strtolower(get_class($this))));
		}
		
		public function export_orders($format = null)
		{
			$this->list_name = get_class($this).'_index_list';
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('orders.csv', $options);
		}
		
		public function export_orders_and_products($format = null)
		{
			$this->list_name = get_class($this).'_index_list';
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('orders.csv', $options, null, true, array('headerCallback' => array('Shop_Order', 'export_orders_and_products_header'), 'rowCallback' => array('Shop_Order', 'export_orders_and_products_row')));
		}

		public function listPrepareData()
		{
			$obj = Shop_Order::create();
			$this->filterApplyToModel($obj);
			$this->applyIntervalToModel($obj);
			$obj->where('shop_orders.deleted_at is null');
			
			return $obj;
		}
		
		public function export_customers($format = null)
		{
			$this->listExportCsv('customers.csv', array(
				'list_sorting_column'=>'billing_email',
				'iwork'=>$format == 'iwork',
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
			), array($this, 'filter_customer_records'));
		}
		
		public function filter_customer_records($row)
		{
			if (in_array($row->customer_id, $this->processed_customer_ids))
				return false;
			
			$this->processed_customer_ids[] = $row->customer_id;
			return true;
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to add new filters to a report page.
		 * Event handlers should use the controller's filter_filters() method for adding new filters. Please read
		 * {@link http://lemonstand.com/docs/list_filters/ List filters} article for details about back-end list filters.
		 * The following example defines a new filter on the Orders Report page.
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->addEvent('shop:onExtendReportFilters', $this, 'extend_filters');
		 * }
		 *  
		 * function extend_filters($controller) 
		 * {
		 *   if( !($controller instanceof Shop_Orders_Report) )
		 *     return;
		 *  
		 *   $controller->filter_filters['dealer'] = array(
		 *     'name'=>'Dealer', 
		 *     'class_name'=>'DealerFilter_Filter', 
		 *     'prompt'=>'Please choose dealers statuses you want to include to the report. Orders with other dealers will be excluded.', 
		 *     'added_list_title'=>'Added Dealers'
		 *   );
		 * }
		 * </pre>
		 * The filter refers to the custom DealersFilter_Filter filter class, which can be defined as follows:
		 * <pre>
		 * class DealerFilter_Filter extends Db_DataFilter 
		 * {
		 *   public $model_class_name = 'Users_User';
		 *   public $list_columns = array('name');
		 *   
		 *   public function prepareListData() 
		 *   {
		 *     $className = $this->model_class_name;
		 *     $obj = new $className();
		 * 
		 *     return $obj;
		 *   }
		 * 
		 *   public function applyToModel($model, $keys, $context = null) 
		 *   {
		 *     $model->where('customer_calculated_join.created_user_id is not null and customer_calculated_join.created_user_id in (?)', array($keys));
		 *   }
		 *   
		 *   public function asString($keys, $context = null) 
		 *   {
		 *     return 'and shop_customers.created_user_id is not null and shop_customers.created_user_id in '.$this->keysToStr($keys);
		 *   }
		 * }
		 * </pre>
		 * 
		 * @event shop:onExtendReportFilters
		 * @see http://lemonstand.com/docs/list_filters/ List filters
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_GenericReport $controller The back-end controller object.
		 */
		private function event_onExtendReportFilters($controller) {}

	}

?>