<?php

class Shop_Products_Report extends Shop_GenericReport {
	protected $chart_types = array(
		Backend_ChartController::rt_column,
		Backend_ChartController::rt_pie,
		Backend_ChartController::rt_line );

	protected $display_objects = array(
		'amount'       => 'Subtotal',
		'num_of_items' => 'Number of items sold'
	);

	public $filter_filters = array(
		'status'           => array( 'name' => 'Current Order Status', 'class_name' => 'Shop_OrderStatusFilter', 'prompt' => 'Please choose order statuses you want to include to the report. Orders with other statuses will be excluded.', 'added_list_title' => 'Added Statuses' ),
		'products'         => array( 'name' => 'Product', 'class_name' => 'Shop_ProductFilter', 'prompt' => 'Please choose products you want to include to the report. All other products will be excluded.', 'added_list_title' => 'Added Products' ),
		'product_skus'     => array( 'name' => 'Product SKU', 'class_name' => 'Shop_OrderSKUFilter', 'prompt' => 'Please choose product SKUs you want to include in the report. Orders that don\'t contain a product with the selected SKU reference will be excluded. This filter includes SKUs assigned to option matrix selections.', 'added_list_title' => 'Added Product SKUs' ),
		'categories'       => array( 'name' => 'Category', 'class_name' => 'Shop_CategoryFilter', 'prompt' => 'Please choose product categories you want to include to the report. Products from other categories will be excluded.', 'added_list_title' => 'Added Categories' ),
		'groups'           => array( 'name' => 'Group', 'class_name' => 'Shop_CustomGroupFilter', 'cancel_if_all' => false, 'prompt' => 'Please choose product groups you want to include to the report. Products from other groups will be excluded.', 'added_list_title' => 'Added Groups' ),
		'product_types'    => array( 'name' => 'Product type', 'class_name' => 'Shop_ProductTypeFilter', 'prompt' => 'Please choose product types you want to include to the report. Products of other types will be excluded.', 'added_list_title' => 'Added Types' ),
		'customer_group'   => array( 'name' => 'Customer Group', 'class_name' => 'Shop_CustomerGroupFilter', 'prompt' => 'Please choose customer groups you want to include to the list. Customers belonging to other groups will be hidden.', 'added_list_title' => 'Added Customer Groups' ),
		'coupon'           => array( 'name' => 'Coupon', 'class_name' => 'Shop_CouponFilter', 'prompt' => 'Please choose coupons you want to include to the list. Orders with other coupons will be hidden.', 'added_list_title' => 'Added Coupons', 'cancel_if_all' => false ),
		'manufacturer'     => array( 'name' => 'Manufacturer', 'class_name' => 'Shop_ManufacturerFilter', 'prompt' => 'Please choose manufacturers you want to include to the list. Products of other manufacturers will be hidden.', 'added_list_title' => 'Added Manufactures' ),
		'billing_country'  => array( 'name' => 'Billing country', 'class_name' => 'Shop_OrderBillingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title' => 'Added Countries' ),
		'shipping_country' => array( 'name' => 'Shipping country', 'class_name' => 'Shop_OrderShippingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title' => 'Added Countries' ),
		'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones')
	);

	protected $timeline_charts = array( Backend_ChartController::rt_line );

	protected $grouped_name_sql = "if (shop_products.grouped = 1, concat(shop_products.name, ' (', shop_products.grouped_option_desc,')'), shop_products.name)";
	protected $stock_name_sql = "if (shop_option_matrix_records.sku = '' OR shop_option_matrix_records.sku IS NULL, :grouped_name  , CONCAT(:grouped_name, ' (' ,shop_option_matrix_options.option_value, ')' ))";


	public function index() {
		$this->app_page_title     = 'Products';
		$this->viewData['report'] = 'products';
		$this->app_module_name    = 'Shop Report';
	}

	protected function onBeforeChartRender() {
		$chartType = $this->getChartType();

		if ( $chartType != Backend_ChartController::rt_column ) {
			return;
		}
	}

	public function refererName() {
		return 'Products Report';
	}

	protected function getChartData(){
		$data = array();
		$series = array();

		$chartType = $this->viewData['chart_type'] = $this->getChartType();

		$filterStr = $this->filterAsString('product_report');

		$paidFilter = $this->getOrderPaidStatusFilter();
		if ( $paidFilter ) {
			$paidFilter = 'and ' . $paidFilter;
		}

		$displayType = $this->getReportParameter( 'product_report_display_type', 'amount' );
		if ( $displayType == 'amount' ) {
			$amountField = 'sum(((shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)*shop_order_items.quantity) * shop_orders.shop_currency_rate)';
		} else {
			$amountField = 'sum(shop_order_items.quantity)';
		}

		if ( $chartType == Backend_ChartController::rt_column || $chartType == Backend_ChartController::rt_pie ) {
			$intervalLimit = $this->intervalQueryStr( false );

			$data_query = "
				select 
					COALESCE(shop_option_matrix_records.sku, shop_products.sku) AS graph_code, 
					'serie' as series_id, 
					'serie' as series_value, 
					".$this->get_stock_name_sql()." AS graph_name, 
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

		} else {
			$intervalLimit    = $this->intervalQueryStr();
			$seriesIdField    = $this->timeSeriesIdField();
			$seriesValueField = $this->timeSeriesValueField();

			// if ($paidFilter)
			// 	$paidFilter = 'and '.$paidFilter;

			$data_query = "
					select
						COALESCE(shop_option_matrix_records.sku, shop_products.sku) AS graph_code, 
						".$this->get_stock_name_sql()." AS graph_name, 
						{$seriesIdField} as series_id,
						{$seriesValueField} as series_value,
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
						(
							(shop_orders.deleted_at is null and
							shop_order_statuses.id = shop_orders.status_id
							$paidFilter
							$filterStr
							)
							or shop_orders.id is null
						)

						and $intervalLimit
					group by {$seriesIdField}, graph_code
					order by report_date, shop_products.id, shop_option_matrix_records.id
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

			$series = Db_DbHelper::objectArray( $series_query );
		}

		$bind                         = array();
		$data = Db_DbHelper::objectArray( $data_query, $bind );

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

	protected function renderReportTotals() {
		$intervalLimit = $this->intervalQueryStrOrders();
		$filterStr     = $this->filterAsString( 'product_report' );

		$paidFilter = $this->getOrderPaidStatusFilter();
		if ( $paidFilter ) {
			$paidFilter = 'and ' . $paidFilter;
		}

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

		$this->viewData['totals_data'] = Db_DbHelper::object( $query );
		$this->renderPartial( 'chart_totals' );
	}

	protected function get_stock_name_sql(){
		return str_replace(':grouped_name', $this->grouped_name_sql, $this->stock_name_sql);
	}
}

?>