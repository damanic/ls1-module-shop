<?php

	class Shop_Stock_Report extends Shop_GenericReport
	{
		public $list_model_class = 'Shop_Product';
		public $list_no_data_message = 'No products found';
		public $list_data_context = null;
		public $list_no_setup_link = true;
		public $list_columns = array('stock_name', 'stock_sku', 'items_ordered', 'stock_in_stock');
		
		protected $chart_types = array(
			Backend_ChartController::rt_column,
			Backend_ChartController::rt_pie);
			
		public $filter_filters = array(
			'status'=>array('name'=>'Current Order Status', 'class_name'=>'Shop_OrderStatusFilter', 'prompt'=>'Please choose order statuses you want to include to the report. Orders with other statuses will be excluded.', 'added_list_title'=>'Added Statuses'),
			'products'=>array('name'=>'Product', 'class_name'=>'Shop_ProductFilter', 'prompt'=>'Please choose products you want to include to the report. All other products will be excluded.', 'added_list_title'=>'Added Products'),
			'product_skus'=>array('name'=>'Product SKU', 'class_name'=>'Shop_OrderSKUFilter', 'prompt'=>'Please choose product SKUs you want to include in the report. Orders that don\'t contain a product with the selected SKU reference will be excluded. This filter includes SKUs assigned to option matrix selections.', 'added_list_title'=>'Added Product SKUs'),
			'categories'=>array('name'=>'Category', 'class_name'=>'Shop_CategoryFilter', 'prompt'=>'Please choose product categories you want to include to the report. Products from other categories will be excluded.', 'added_list_title'=>'Added Categories'),
			'groups'=>array('name'=>'Group', 'class_name'=>'Shop_CustomGroupFilter', 'cancel_if_all'=>false, 'prompt'=>'Please choose product groups you want to include to the report. Products from other groups will be excluded.', 'added_list_title'=>'Added Groups'),
			'product_types'=>array('name'=>'Product type', 'class_name'=>'Shop_ProductTypeFilter', 'prompt'=>'Please choose product types you want to include to the report. Products of other types will be excluded.', 'added_list_title'=>'Added Types'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'coupon'=>array('name'=>'Coupon', 'class_name'=>'Shop_CouponFilter', 'prompt'=>'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title'=>'Added Coupons', 'cancel_if_all'=>false),
			'manufacturer'=>array('name'=>'Manufacturer', 'class_name'=>'Shop_ManufacturerFilter', 'prompt'=>'Please choose manufacturers you want to include to the list. Products of other manufacturers will be hidden.', 'added_list_title'=>'Added Manufactures'),
			'billing_country'=>array('name'=>'Billing country', 'class_name'=>'Shop_OrderBillingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones')
		);

		protected $grouped_name_sql = "if (shop_products.grouped = 1, concat(shop_products.name, ' (', shop_products.grouped_option_desc,')'), shop_products.name)";
		protected $stock_name_sql = "if (shop_option_matrix_records.sku = '' OR shop_option_matrix_records.sku IS NULL, :grouped_name  , CONCAT(:grouped_name, ' (' ,shop_option_matrix_options.option_value, ')' ))";

		public function __construct()
		{
			parent::__construct();

			$user = Phpr::$security->getUser();
			if ($user && $user->get_permission('shop', 'manage_products'))
				$this->list_record_url = url('/shop/products/edit/%s/');
				
			$this->list_control_panel_partial = PATH_APP.'/modules/shop/controllers/shop_stock_report/_reports_export_buttons.htm';
		}

		public function index()
		{
			$this->app_page_title = 'Stock';
			$this->viewData['report'] = 'stock';
			$this->app_module_name = 'Shop Report';
		}
		
		protected function onBeforeChartRender()
		{
			$chartType = $this->getChartType();
			
			if ($chartType != Backend_ChartController::rt_column)
				return;
		}
		
		public function export_products($format = null)
		{
			$options = array();
			$options['iwork'] = $format == 'iwork';
			$this->listExportCsv('stock.csv', $options);
		}
		
		public function listFormatRecordUrl($model)
		{
			if (!($model instanceof Shop_Product))
			{
				$extension = $this->getExtension('Db_ListBehavior');
				return $extension->listGetTotalItemNumber($model);
			}

			if ($model->grouped)
				return url('/shop/products/edit/'.$model->product_id.'/shop_stock_report');
			else
				return url('/shop/products/edit/'.$model->id.'/shop_stock_report/');
		}
		
		public function listPrepareData()
		{
			$obj = Shop_Product::create();
			$obj->calculated_columns['items_ordered'] = array('sql'=>"sum(shop_order_items.quantity)", 'type'=>db_number);
			$obj->calculated_columns['stock_sku'] = array('sql'=>"COALESCE(shop_option_matrix_records.sku, shop_products.sku)", 'type'=>db_varchar);
			$obj->calculated_columns['stock_in_stock'] = array('sql'=>"COALESCE(shop_option_matrix_records.in_stock, shop_products.in_stock)", 'type'=>db_number);
			$obj->calculated_columns['stock_name'] = array('sql'=>$this->get_stock_name_sql(), 'type'=>db_varchar);

			$this->list_data_context = 'stock_report_data';

			$obj->join('shop_order_items', 'shop_products.id=shop_order_items.shop_product_id');
			$obj->join('shop_orders', 'shop_orders.id=shop_order_items.shop_order_id');
			$obj->join('shop_customers', 'shop_customers.id=shop_orders.customer_id');
			$obj->join('shop_option_matrix_records', 'shop_order_items.option_matrix_record_id = shop_option_matrix_records.id');
			$obj->join('shop_option_matrix_options', 'shop_option_matrix_options.matrix_record_id = shop_option_matrix_records.id');

			$obj->group('COALESCE(shop_option_matrix_records.sku, shop_products.sku)');
			$this->filterApplyToModel($obj, 'product_report');
			$this->applyIntervalToModel($obj);
			return $obj;
		}
		
		public function listExtendModelObject($model)
		{
			if ($model instanceof Shop_Product)
			{
				$this->list_data_context = 'stock_report_data';
				$model->calculated_columns['items_ordered'] = array('sql'=>"sum(shop_order_items.quantity)", 'type'=>db_number);

				$model->define_column('stock_sku', 'SKU')->invisible();
				$model->calculated_columns['stock_sku'] = array('sql'=>"COALESCE(shop_option_matrix_records.sku, shop_products.sku)", 'type'=>db_varchar);

				$model->define_column('stock_name', 'SKU')->invisible();
				$model->calculated_columns['stock_name'] = array('sql'=>$this->get_stock_name_sql(), 'type'=>db_varchar);

				$model->define_column('stock_in_stock', 'Units In Stock')->invisible();
				$model->calculated_columns['stock_in_stock'] = array('sql'=>"COALESCE(shop_option_matrix_records.in_stock, shop_products.in_stock)", 'type'=>db_number);

			}
				
			return $model;
		}
		
		public function listGetTotalItemNumber($model)
		{
			if (post('filter_id_value') || post('filter_request'))
			{
				$extension = $this->getExtension('Db_ListBehavior');
				return $extension->listGetTotalItemNumber($model);
			}
			
			$model->parts['fields'] = array('count(distinct shop_products.id)');
			if (isset($model->parts['group']))
				unset($model->parts['group']);
		
			$sql = $model->build_sql();
			$sql = str_replace(', shop_products.*', '', $sql);
		
			return Db_Sql::create()->fetchOne($sql); 
		}
		
		public function refererName()
		{
			return 'Stock Report';
		}
		
		public function listGetRowClass($model)
		{
			if ($model instanceof Shop_Product)
			{
				if (!$model->inventory_tracking_enabled())
					return;
					
				if ($model->items_ordered > $model->stock_in_stock)
					return 'important error';
			}
		}

		public function getChartData()
		{
			$data = array();
			$series = array();

			$chartType = $this->viewData['chart_type'] = $this->getChartType();

			$filterStr = $this->filterAsString('product_report');

			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$displayType = $this->getReportParameter('product_report_display_type', 'num_of_items');
			if ($displayType == 'amount')
				$amountField = 'sum(((shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)*shop_order_items.quantity) * shop_orders.shop_currency_rate)';
			else
				$amountField = 'sum(shop_order_items.quantity)';

			$intervalLimit = $this->intervalQueryStr(false);

			$data_query = "
			select 
						COALESCE(shop_option_matrix_records.sku, shop_products.sku) AS graph_code, 
						CONCAT(COALESCE(shop_option_matrix_records.sku, shop_products.sku) , ' | ', ".$this->get_stock_name_sql().") AS graph_name, 
						'serie' as series_id, 
						'serie' as series_value, 
						$amountField as record_value
			from 
				shop_order_statuses,
				report_dates
				left join shop_orders on report_date = shop_orders.order_date
				left join shop_order_items on shop_order_items.shop_order_id = shop_orders.id
				left join shop_products on shop_products.id = shop_order_items.shop_product_id	   
				LEFT JOIN shop_option_matrix_records ON shop_order_items.option_matrix_record_id = shop_option_matrix_records.id
				LEFT JOIN shop_option_matrix_options ON shop_option_matrix_options.matrix_record_id = shop_option_matrix_records.id
				left join shop_customers on shop_customers.id=shop_orders.customer_id
			where
				shop_orders.deleted_at is null and
				shop_order_statuses.id = shop_orders.status_id and
				$intervalLimit
				$filterStr
				$paidFilter
				GROUP BY graph_code
				order by report_date, shop_products.id, shop_option_matrix_records.id
		";

			$bind = array();
			$data = Db_DbHelper::objectArray($data_query, $bind);
			return array(
				'data' =>$data,
				'series' => $series
			);
		}

		public function chart_data() {
			$this->xmlData();
			$result = $this->getChartData();
			$this->viewData['chart_data'] = $result['data'];
			$this->viewData['chart_series'] = $result['series'];
		}


		protected function renderReportTotals()
		{
			$intervalLimit = $this->intervalQueryStrOrders();
			$filterStr     = $this->filterAsString( 'product_report' );
			
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$query_str = "FROM shop_orders
							LEFT JOIN shop_order_items 
							ON shop_order_items.shop_order_id = shop_orders.id 
							LEFT JOIN shop_order_statuses
							ON shop_order_statuses.id = shop_orders.status_id 
							LEFT JOIN shop_products
							ON shop_products.id = shop_order_items.shop_product_id 
							LEFT JOIN shop_option_matrix_records
							ON shop_order_items.option_matrix_record_id = shop_option_matrix_records.id
							LEFT JOIN shop_customers 
							ON shop_customers.id=shop_orders.customer_id  
						    WHERE $intervalLimit $filterStr $paidFilter";

			$query = "
				select ifnull((select sum(shop_order_items.quantity) $query_str), 0) as items_sold,
				(select sum(((shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)*shop_order_items.quantity) * shop_orders.shop_currency_rate) $query_str) as amount
			";

			$this->viewData['totals_data'] = Db_DbHelper::object($query);
			$this->renderPartial('chart_totals');
		}


		protected function get_stock_name_sql(){
			return str_replace(':grouped_name', $this->grouped_name_sql, $this->stock_name_sql);
		}

	}

?>