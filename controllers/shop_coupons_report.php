<?php

	class Shop_Coupons_Report extends Shop_GenericReport
	{
		protected $chart_types = array(
			Backend_ChartController::rt_column,
			Backend_ChartController::rt_pie,
			Backend_ChartController::rt_line);
			
		public $filter_filters = array(
			'status'=>array('name'=>'Current Order Status', 'class_name'=>'Shop_OrderStatusFilter', 'prompt'=>'Please choose order statuses you want to include to the report. Orders with other statuses will be excluded.', 'added_list_title'=>'Added Statuses'),
			'customer_group'=>array('name'=>'Customer Group', 'class_name'=>'Shop_CustomerGroupFilter', 'prompt'=>'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title'=>'Added Customer Groups'),
			'coupon'=>array('name'=>'Coupon', 'class_name'=>'Shop_CouponFilter', 'prompt'=>'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title'=>'Added Coupons', 'cancel_if_all'=>false),
			'billing_country'=>array('name'=>'Billing country', 'class_name'=>'Shop_OrderBillingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones')
		);
		
		protected $timeline_charts = array(Backend_ChartController::rt_line);

		public function index()
		{
			$this->app_page_title = 'Coupon Usage';
			$this->viewData['report'] = 'coupons';
			$this->app_module_name = 'Shop Report';
		}
		
		protected function onBeforeChartRender()
		{
			$chartType = $this->getChartType();
			
			if ($chartType != Backend_ChartController::rt_column)
				return;
		}
		
		public function listPrepareData()
		{
			$obj = Shop_Order::create();
			$this->filterApplyToModel($obj);
			$this->applyIntervalToModel($obj);
			$obj->where('shop_orders.deleted_at is null');
			$obj->where('shop_orders.coupon_id is not null');
			
			return $obj;
		}
		
		public function refererName()
		{
			return 'Coupon Usage Report';
		}

		public function getChartData()
		{

			$data = array();
			$series = array();

			$chartType = $this->viewData['chart_type'] = $this->getChartType();
			
			$filterStr = $this->filterAsString();

			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;

			$displayType = $this->getReportParameter('coupon_report_display_type', 'amount');
			$amountField = $this->getOrderAmountField();

			if ($chartType == Backend_ChartController::rt_column || $chartType == Backend_ChartController::rt_pie)
			{
				$intervalLimit = $this->intervalQueryStr(false);

				$data_query = "
				select 
					shop_coupons.id as graph_code, 
					'serie' as series_id, 
					'serie' as series_value, 
					shop_coupons.code as graph_name, 
					sum($amountField * shop_orders.shop_currency_rate) as record_value
				from 
					shop_order_statuses,
					report_dates
				left join shop_orders on report_date = shop_orders.order_date
				left join shop_coupons on shop_coupons.id = shop_orders.coupon_id
				left join shop_customers on shop_customers.id=shop_orders.customer_id
				where
					shop_orders.deleted_at is null and
					shop_order_statuses.id = shop_orders.status_id and
					shop_orders.coupon_id is not null and
					$intervalLimit
					$filterStr
					$paidFilter
				group by shop_coupons.id
				order by report_date, shop_coupons.id
			";

			} else
			{
				$intervalLimit = $this->intervalQueryStr();
				$seriesIdField = $this->timeSeriesIdField();
				$seriesValueField = $this->timeSeriesValueField();

				$data_query = "
					select
						shop_coupons.id as graph_code,
						shop_coupons.code as graph_name,
						{$seriesIdField} as series_id,
						{$seriesValueField} as series_value,
						sum($amountField * shop_orders.shop_currency_rate) as record_value
					from 
						shop_order_statuses,
						report_dates
					left join shop_orders on report_date = shop_orders.order_date
					left join shop_coupons on shop_coupons.id = shop_orders.coupon_id
					left join shop_customers on shop_customers.id=shop_orders.customer_id

					where 
						(
							(shop_orders.deleted_at is null and
							shop_order_statuses.id = shop_orders.status_id and
							shop_orders.coupon_id is not null
							$paidFilter
							$filterStr)
							or shop_orders.id is null
						)

					and $intervalLimit

					group by {$seriesIdField}, shop_coupons.id
					order by report_date, shop_coupons.id
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
				$series = Db_DbHelper::objectArray($series_query);
			}

			$bind = array();
			$data = Db_DbHelper::objectArray($data_query, $bind);
			return array(
				'data' => $data,
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
			$filterStr = $this->filterAsString();
			
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;
				
			$query_str = "from shop_orders, shop_order_statuses, shop_customers
			where shop_customers.id=customer_id and shop_orders.coupon_id is not null and shop_order_statuses.id = shop_orders.status_id and $intervalLimit $filterStr $paidFilter";

			$query = "
				select (select count(*) $query_str) as order_num,
				(select sum(total * shop_orders.shop_currency_rate) $query_str) as total,
				(select sum((total - goods_tax - shipping_tax - shipping_quote) * shop_orders.shop_currency_rate) $query_str) as revenue,
				(select sum((goods_tax + shipping_tax) * shop_orders.shop_currency_rate) $query_str) as tax,
				(select sum(shipping_quote * shop_orders.shop_currency_rate) $query_str) as shipping
			";
			
			$this->viewData['totals_data'] = Db_DbHelper::object($query);
			$this->renderPartial('chart_totals');
		}
	}

?>