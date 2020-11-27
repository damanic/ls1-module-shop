<?php

	class Shop_Taxes_Report extends Shop_GenericReport
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
			'billing_state'=>array('name'=>'Billing State', 'class_name'=>'Shop_CustomerBillingStateFilter', 'prompt'=>'Please choose billing states you want to include to the list. Orders with other billing states will be hidden.', 'added_list_title'=>'Added States'),
			'shipping_country'=>array('name'=>'Shipping country', 'class_name'=>'Shop_OrderShippingCountryFilter', 'prompt'=>'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title'=>'Added Countries'),
			'shipping_state'=>array('name'=>'Shipping State', 'class_name'=>'Shop_CustomerShippingStateFilter', 'prompt'=>'Please choose shipping states you want to include to the list. Orders with other shipping states will be hidden.', 'added_list_title'=>'Added States'),
			'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones')
		);
		
		public function index()
		{
			$this->app_page_title = 'Taxes';
			$this->viewData['report'] = 'taxes';
			$this->app_module_name = 'Shop Report';
		}
		
		public function refererName()
		{
			return 'Taxes Report';
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
				$query = "
					select
						sales_taxes, shipping_tax_1, shipping_tax_2, shipping_tax_name_1, shipping_tax_name_2
					from
						shop_order_statuses,
						report_dates
					left join shop_orders on report_date = shop_orders.order_date
					left join shop_customers on shop_customers.id=shop_orders.customer_id
					left join shop_coupons on shop_coupons.id = shop_orders.coupon_id
					where
						shop_orders.deleted_at is null and
						shop_order_statuses.id = shop_orders.status_id and
						$intervalLimit
						$filterStr
						$paidFilter
					order by report_date
				";
				$orders = Db_DbHelper::queryArray($query);
				if(count($orders))
				{
					$taxes = array();
					foreach($orders as $order)
					{
						if($order['shipping_tax_name_1'])
							$taxes = $this->add_to_tax($taxes, $order['shipping_tax_name_1'], $order['shipping_tax_1']);
						if($order['shipping_tax_name_2'])
							$taxes = $this->add_to_tax($taxes, $order['shipping_tax_name_2'], $order['shipping_tax_2']);
						$sales_taxes = unserialize($order['sales_taxes']);
						if (is_array($sales_taxes))
						{
							foreach ($sales_taxes as $tax_name=>$tax_info)
							{
								if ($tax_info->total > 0)
									$taxes = $this->add_to_tax($taxes, $tax_name, $tax_info->total);
							}
						}
					}
					
					if(count($taxes))
					{
						$i=0;
						foreach ($taxes as $tax_name => $tax_value)
						{
							$new = array('graph_code' => $i++, 'series_id' => 'serie', 'series_value' => 'serie', 'graph_name' => $tax_name, 'record_value' => $tax_value['value']);
							array_push($data, (object) $new);
						}
					}
				}
			}
			elseif($chartType == Backend_ChartController::rt_line)
			{
				$intervalLimit = $this->intervalQueryStr();
				$query = "
					select
						sales_taxes, shipping_tax_1, shipping_tax_2, shipping_tax_name_1, shipping_tax_name_2, report_date
					from
						shop_order_statuses,
						report_dates
					left join shop_orders on report_date = shop_orders.order_date
					left join shop_customers on shop_customers.id=shop_orders.customer_id
					left join shop_coupons on shop_coupons.id = shop_orders.coupon_id
					where
						shop_orders.deleted_at is null and
						shop_order_statuses.id = shop_orders.status_id and
						$intervalLimit
						$filterStr
						$paidFilter
					order by report_date
				";
				
				$seriesIdField = $this->timeSeriesIdField();
				$seriesValueField = $this->timeSeriesValueField();
				$series_query = "
					select
						null as graph_code,
						null as graph_name,
						{$seriesIdField} as series_id,
						{$seriesValueField} as series_value,
						null as record_value
					from report_dates
					where
						$intervalLimit
					order by report_date
				";
				$series = Db_DbHelper::queryArray($series_query);
				$orders = Db_DbHelper::queryArray($query);
				if(count($orders) && count($series))
				{
					$taxes = array();
					
					foreach($orders as $order)
					{
						if($order['shipping_tax_name_1'])
							$taxes = $this->add_to_tax_day($taxes, $order['shipping_tax_name_1'], $order['shipping_tax_1'], $order['report_date']);
						if($order['shipping_tax_name_2'])
							$taxes = $this->add_to_tax_day($taxes, $order['shipping_tax_name_2'], $order['shipping_tax_2'], $order['report_date']);
						$sales_taxes = unserialize($order['sales_taxes']);
						if (is_array($sales_taxes))
						{
							foreach ($sales_taxes as $tax_name=>$tax_info)
							{
								if ($tax_info->total > 0)
									$taxes = $this->add_to_tax_day($taxes, $tax_name, $tax_info->total, $order['report_date']);
							}
						}
					}
					if(count($taxes))
					{
						$new_taxes = array();
						$i=0;
						foreach($taxes as $d=>$c)
						{
							foreach ($c as $a => $b)
							{
								if($b['value'] > 0)
									$new_taxes[$i++] = array(
										'report_date' => $d,
										'tax_name' => $a,
										'tax_value' => $b['value']
									);
							}
						}
						$data = $this->combine_series_taxes($new_taxes, $series);
					}
				}
				
				$series = Db_DbHelper::objectArray($series_query);
			}

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
		
		private function combine_series_taxes($taxes, $series)
		{
			$result = array();
			reset($taxes);
			$tax = current($taxes);
			foreach($series as $k=>$s)
			{
				if($tax['report_date'] == $s['series_value'])
					while ($tax['report_date'] == $s['series_value'])
					{
						$result[] = (object)array (
							'graph_code' =>$tax['tax_name'],
							'graph_name' => $tax['tax_name'],
							'series_id' => $s['series_id'],
							'series_value' => $s['series_value'],
							'record_value' => $tax['tax_value']
						);
						next($taxes);
						$tax = current($taxes);
					}
				
				else $result[] = (object)array (
					'graph_code' =>null,
					'graph_name' => null,
					'series_id' => $s['series_id'],
					'series_value' => $s['series_value'],
					'record_value' => null
				);
			}
			return $result;
		}
		
		private function add_to_tax_day($tax_array, $tax_name, $tax_value, $tax_date)
		{
			if($tax_value > 0)
			{
				if(array_key_exists($tax_date, $tax_array))
				{
					if(array_key_exists($tax_name, $tax_array[$tax_date]))
						$tax_array[$tax_date][$tax_name]['value'] = $tax_array[$tax_date][$tax_name]['value'] + $tax_value;
					else
						$tax_array[$tax_date][$tax_name] = array('value' => $tax_value);
				}
				else
				{
					$tax_array[$tax_date][$tax_name] = array('value' => $tax_value);
				}
			}
			return $tax_array;
		}

		private function add_to_tax($tax_array, $tax_name, $tax_value)
		{
			if($tax_value > 0)
			{
				if(array_key_exists($tax_name, $tax_array))
				{
					//add tax to existing array
					$tax_array[$tax_name]['value'] = $tax_array[$tax_name]['value'] + $tax_value;
				}
				else
				{
					//add to array
					$tax_array[$tax_name] = array('value' => $tax_value);
				}
			}
			return $tax_array;
		}
		
		protected function renderReportTotals()
		{
			$intervalLimit = $this->intervalQueryStrOrders();
			$filterStr = $this->filterAsString();
			
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;
			
			$query = "
				select
					sales_taxes, shipping_tax_1, shipping_tax_2, shipping_tax_name_1, shipping_tax_name_2
				from
					shop_order_statuses,
					report_dates
				left join shop_orders on report_date = shop_orders.order_date
				left join shop_customers on shop_customers.id=shop_orders.customer_id
				left join shop_coupons on shop_coupons.id = shop_orders.coupon_id
				where
					shop_orders.deleted_at is null and
					shop_order_statuses.id = shop_orders.status_id and
					$intervalLimit
					$filterStr
					$paidFilter
				order by report_date
			";
			
			$orders = Db_DbHelper::queryArray($query);
			if(count($orders))
			{
				$taxes = array();
				foreach($orders as $order)
				{
					if($order['shipping_tax_name_1'])
						$taxes = $this->add_to_tax($taxes, $order['shipping_tax_name_1'], $order['shipping_tax_1']);
					if($order['shipping_tax_name_2'])
						$taxes = $this->add_to_tax($taxes, $order['shipping_tax_name_2'], $order['shipping_tax_2']);
					$sales_taxes = unserialize($order['sales_taxes']);
					if (is_array($sales_taxes))
					{
						foreach ($sales_taxes as $tax_name=>$tax_info)
						{
							if ($tax_info->total > 0)
								$taxes = $this->add_to_tax($taxes, $tax_name, $tax_info->total);
						}
					}
				}
				
				if(count($taxes))
				{
					$this->viewData['totals_data'] = $taxes;
				}
			}
			
			$this->renderPartial('chart_totals');
		}
	}
?>