<?php

	class Shop_Stock_Report extends Shop_GenericReport
	{
		public $list_model_class = 'Shop_Product';
		public $list_no_data_message = 'No products found';
		public $list_data_context = null;
		public $list_no_setup_link = true;
		public $list_columns = array('grouped_name', 'sku', 'items_ordered', 'in_stock');
		
		protected $chart_types = array(
			Backend_ChartController::rt_column,
			Backend_ChartController::rt_pie);
			
		public $filter_filters = array(
			'status'=>array('name'=>'Current Order Status', 'class_name'=>'Shop_OrderStatusFilter', 'prompt'=>'Please choose order statuses you want to include to the report. Orders with other statuses will be excluded.', 'added_list_title'=>'Added Statuses'),
			'products'=>array('name'=>'Product', 'class_name'=>'Shop_ProductFilter', 'prompt'=>'Please choose products you want to include to the report. All other products will be excluded.', 'added_list_title'=>'Added Products'),
			'categories'=>array('name'=>'Category', 'class_name'=>'Shop_CategoryFilter', 'prompt'=>'Please choose product categories you want to include to the report. Products from other categories will be excluded.', 'added_list_title'=>'Added Categories'),
			'groups'=>array('name'=>'Group', 'class_name'=>'Shop_CustomGroupFilter', 'cancel_if_all'=>false, 'prompt'=>'Please choose product groups you want to include to the report. Products from other groups will be excluded.', 'added_list_title'=>'Added Groups'),
			'product_types'=>array('name'=>'Product type', 'class_name'=>'Shop_ProductTypeFilter', 'prompt'=>'Please choose product types you want to include to the report. Products of other types will be excluded.', 'added_list_title'=>'Added Types'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'coupon'=>array('name'=>'Coupon', 'class_name'=>'Shop_CouponFilter', 'prompt'=>'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title'=>'Added Coupons', 'cancel_if_all'=>false),
			'manufacturer'=>array('name'=>'Manufacturer', 'class_name'=>'Shop_ManufacturerFilter', 'prompt'=>'Please choose manufacturers you want to include to the list. Products of other manufacturers will be hidden.', 'added_list_title'=>'Added Manufactures'),
			'billing_country'=>array('name'=>'Billing country', 'class_name'=>'Shop_OrderBillingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries')
		);

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
			$this->list_data_context = 'stock_report_data';

			$obj->join('shop_order_items', 'shop_products.id=shop_order_items.shop_product_id');
			$obj->join('shop_orders', 'shop_orders.id=shop_order_items.shop_order_id');
			$obj->join('shop_customers', 'shop_customers.id=shop_orders.customer_id');
			
			$obj->group('shop_products.id');
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
				if (!$model->track_inventory)
					return;
					
				if ($model->items_ordered > $model->in_stock)
					return 'important error';
			}
		}

		public function chart_data()
		{
			$this->xmlData();
			$chartType = $this->viewData['chart_type'] = $this->getChartType();
			
			$filterStr = $this->filterAsString();

			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$displayType = $this->getReportParameter('product_report_display_type', 'num_of_items');
			if ($displayType == 'amount')
				$amountField = 'sum((shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)*shop_order_items.quantity)';
			else
				$amountField = 'sum(shop_order_items.quantity)';

			$intervalLimit = $this->intervalQueryStr(false);

			$query = "
			select 
				shop_products.id as graph_code, 
				'serie' as series_id, 
				'serie' as series_value, 
				concat(shop_products.sku, ', ', shop_products.name) as graph_name, 
				$amountField as record_value
			from 
				shop_order_statuses,
				report_dates
			left join shop_orders on report_date = shop_orders.order_date
			left join shop_order_items on shop_order_items.shop_order_id = shop_orders.id
			left join shop_products on shop_products.id = shop_order_items.shop_product_id
			left join shop_customers on shop_customers.id=shop_orders.customer_id
			where
				shop_orders.deleted_at is null and
				shop_order_statuses.id = shop_orders.status_id and
				$intervalLimit
				$filterStr
				$paidFilter
			group by shop_products.id
			order by report_date, shop_products.id
		";

			$bind = array();
			$this->viewData['chart_data'] = Db_DbHelper::objectArray($query, $bind);
		}
		
		protected function renderReportTotals()
		{
			$intervalLimit = $this->intervalQueryStrOrders();
			$filterStr = $this->filterAsString();
			
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;
				
			$query_str = "from shop_orders, shop_order_statuses, shop_order_items, shop_products, shop_customers where shop_customers.id=customer_id and shop_orders.deleted_at is null and shop_products.id = shop_order_items.shop_product_id and shop_order_statuses.id = shop_orders.status_id and shop_order_items.shop_order_id = shop_orders.id and $intervalLimit $filterStr $paidFilter";

			$query = "
				select ifnull((select sum(shop_order_items.quantity) $query_str), 0) as items_sold,
				(select sum((shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)*shop_order_items.quantity) $query_str) as amount
			";

			$this->viewData['totals_data'] = Db_DbHelper::object($query);
			$this->renderPartial('chart_totals');
		}
	}

?>