<?php

	class Shop_Orders_Report extends Shop_GenericReport
	{
		protected $chart_types = array( 
			Backend_ChartController::rt_line);

		public $filter_filters = array(
			'status'=>array('name'=>'Current Order Status', 'class_name'=>'Shop_OrderStatusFilter', 'prompt'=>'Please choose order statuses you want to include to the report. Orders with other statuses will be excluded.', 'added_list_title'=>'Added Statuses'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'coupon'=>array('name'=>'Coupon', 'class_name'=>'Shop_CouponFilter', 'prompt'=>'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title'=>'Added Coupons', 'cancel_if_all'=>false),
			'billing_country'=>array('name'=>'Billing country', 'class_name'=>'Shop_OrderBillingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones'),
			'products'=>array('name'=>'Product', 'class_name'=>'Shop_OrderProductFilter', 'prompt'=>'Please choose products you want to include to the report. Orders that don\'t contain the selected products will be excluded.', 'added_list_title'=>'Added Products'),
			'product_skus'=>array('name'=>'Product SKU', 'class_name'=>'Shop_OrderSKUFilter', 'prompt'=>'Please choose product SKUs you want to include in the report. Orders that don\'t contain a product with the selected SKU reference will be excluded. This filter includes SKUs assigned to option matrix selections.', 'added_list_title'=>'Added Product SKUs'),
		);
		
		protected $timeline_charts = array(Backend_ChartController::rt_line);

		public function index()
		{
			$this->app_page_title = 'Orders';
			$this->viewData['report'] = 'orders';
			$this->app_module_name = 'Shop Report';
		}
		
		protected function onBeforeChartRender()
		{
			$chartType = $this->getChartType();
			
			if ($chartType != Backend_ChartController::rt_line)
				return;
		}
		
		public function refererName()
		{
			return 'Orders Report';
		}

		protected function getChartData(){

			$chartType = $this->viewData['chart_type'] = $this->getChartType();
			$filterStr = $this->filterAsString();
			$amountField = $this->getOrderAmountField();
			$paidFilter = $this->getOrderPaidStatusFilter();

			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$intervalLimit = $this->intervalQueryStr();
			$seriesIdField = $this->timeSeriesIdField();
			$seriesValueField = $this->timeSeriesValueField();
			$frameFields = $this->timeSeriesDateFrameFields();

			$data_query = "
				select
					'amount' as graph_code,
					'amount' as graph_name,
					{$seriesIdField} as series_id,
					{$seriesValueField} as series_value,
					sum($amountField * shop_orders.shop_currency_rate) as record_value
					$frameFields
				from 
					shop_order_statuses,
					report_dates
				left join shop_orders on report_date = shop_orders.order_date
				left join shop_customers on shop_customers.id=shop_orders.customer_id
		
				where
					(
						(shop_orders.deleted_at is null and
						shop_order_statuses.id = shop_orders.status_id
						$paidFilter
						$filterStr
						) 
						or shop_orders.id is null
					)
					and $intervalLimit

				group by {$seriesIdField}
				order by report_date
			";

			$series_query = "
				select
					{$seriesIdField} as series_id,
					{$seriesValueField} as series_value
				from report_dates
				where 
					$intervalLimit
				order by report_date
			";

			$bind = array();
			$result = array(
				'data' => Db_DbHelper::objectArray($data_query, $bind),
				'series' => Db_DbHelper::objectArray($series_query, $bind)
			);
			return $result;
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
			$filterStr = $this->filterAsString();

			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$query_str = "from shop_orders, shop_order_statuses, shop_customers
			where shop_customers.id=customer_id and shop_orders.deleted_at is null and shop_order_statuses.id = shop_orders.status_id and $intervalLimit $filterStr $paidFilter";

			$query = "
				select (select count(*) $query_str) as order_num,
				(select sum(total*shop_orders.shop_currency_rate) $query_str) as total,
				(select sum((total - goods_tax - shipping_tax - shipping_quote - ifnull(total_cost, 0))* shop_orders.shop_currency_rate) $query_str) as revenue,
				(select sum(total_cost* shop_orders.shop_currency_rate) $query_str) as cost,
				(select sum((goods_tax + shipping_tax)* shop_orders.shop_currency_rate) $query_str) as tax,
				(select sum(shipping_quote* shop_orders.shop_currency_rate) $query_str) as shipping
			";

			$this->viewData['totals_data'] = Db_DbHelper::object($query);
			$this->renderPartial('chart_totals');
		}

		
		public static function get_totals_chart_data($start, $end)
		{
			$status_filter = null;
			$settings = Cms_Stats_Settings::get();
			if ($settings->dashboard_paid_only)
			{
				$status_filter = "and (exists (select shop_order_status_log_records.id from shop_order_status_log_records, shop_order_statuses where shop_order_status_log_records.order_id = shop_orders.id and shop_order_statuses.id=shop_order_status_log_records.status_id and shop_order_statuses.code='paid'))";
			}

			$query = "
				select
					'amount' as graph_code,
					'amount' as graph_name,
					report_date as series_id,
					report_dates.report_date as series_value,
					sum(shop_orders.total * shop_orders.shop_currency_rate) as record_value
				from 
					report_dates
				left join shop_orders on report_date = shop_orders.order_date and shop_orders.deleted_at is null $status_filter
				left join shop_order_statuses on shop_order_statuses.id = shop_orders.status_id 
				left join shop_customers on shop_customers.id=shop_orders.customer_id
		
				where 
					report_date >= :start and report_date <= :end

				group by report_date
				order by report_date
			";

			return Db_DbHelper::objectArray($query, array('start'=>$start, 'end'=>$end));
		}
		
		public static function evalTotalsStatistics($start, $end)
		{
			$prevStart = $prevEnd = null;
			Backend_Dashboard::evalPrevPeriod($start, $end, $prevStart, $prevEnd);
			
			return Db_DbHelper::object('
				select 
				(select sum(total * shop_orders.shop_currency_rate) from shop_orders where shop_orders.order_date >= :current_start and shop_orders.order_date <= :current_end and deleted_at is null) as totals_current,
				(select sum(total * shop_orders.shop_currency_rate) from shop_orders where shop_orders.order_date >= :prev_start and shop_orders.order_date <= :prev_end and deleted_at is null) as totals_prev
			', array(
				'current_start'=>$start,
				'current_end'=>$end,
				'prev_start'=>$prevStart,
				'prev_end'=>$prevEnd
			));
		}
		
		public static function evalPaidOrdersStatistics($start, $end)
		{
			$prevStart = $prevEnd = null;
			Backend_Dashboard::evalPrevPeriod($start, $end, $prevStart, $prevEnd);
			
			$status_filter = "and (exists (select shop_order_status_log_records.id from shop_order_status_log_records, shop_order_statuses where shop_order_status_log_records.order_id = shop_orders.id and shop_order_statuses.id=shop_order_status_log_records.status_id and shop_order_statuses.code='paid'))";

			$result = Db_DbHelper::object('
				select 
				(select sum(total * shop_orders.shop_currency_rate) from shop_orders where shop_orders.order_date >= :current_start and shop_orders.order_date <= :current_end and deleted_at is null '.$status_filter.') as totals_current,
				(select sum(total * shop_orders.shop_currency_rate) from shop_orders where shop_orders.order_date >= :prev_start and shop_orders.order_date <= :prev_end and deleted_at is null '.$status_filter.') as totals_prev
			', array(
				'current_start'=>$start,
				'current_end'=>$end,
				'prev_start'=>$prevStart,
				'prev_end'=>$prevEnd
			));
			
			if (!strlen($result->totals_current))
				$result->totals_current = 0;

			if (!strlen($result->totals_prev))
				$result->totals_prev = 0;
			
			return $result;
		}
		
		public static function getRecentOrders($number = 5)
		{
			if (!class_exists('Shop_Order'))
				return new Db_DataCollection();

			$obj = Shop_Order::create();
			$obj->where('shop_orders.deleted_at is null');
			$obj->order('id desc');
			$obj->limit($number);
			
			return $obj->find_all();
		}
	}

?>