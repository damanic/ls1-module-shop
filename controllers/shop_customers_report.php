<?php

class Shop_Customers_Report extends Shop_GenericReport {

	public $list_model_class = 'Shop_Customer';
	public $list_no_data_message = 'No customers found';
	public $list_data_context = null;
	public $list_no_setup_link = false;
	public $list_control_panel_partial = null;



	protected $chart_types = array(
//		Backend_ChartController::rt_column,
//		Backend_ChartController::rt_pie,
		Backend_ChartController::rt_line
	);

	protected $display_objects = array(
		'orders_placed'    => 'Customers who placed orders',
		'orders_paid'      => 'Customers with paid orders',
		'no_orders_placed' => 'Customers with no placed orders',
		'no_orders_paid'   => 'Customers with no paid orders',
		'account_created'  => 'New customers',
	);

	public $filter_filters = array(
		'billing_country'  => array( 'name' => 'Billing country', 'class_name' => 'Shop_OrderBillingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Orders with other billing countries will be hidden.', 'added_list_title' => 'Added Countries' ),
		'shipping_country' => array( 'name' => 'Shipping country', 'class_name' => 'Shop_OrderShippingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Orders with other shipping countries will be hidden.', 'added_list_title' => 'Added Countries' ),
		'shipping_zone'=>array('name'=>'Shipping Zone', 'class_name'=>'Shop_OrderShippingZoneFilter', 'prompt'=>'Please choose shipping zones you want to include in the list. Orders to countries not in the shipping zones will be hidden.', 'added_list_title'=>'Added Shipping Zones')
	);

	protected $timeline_charts = array(
		Backend_ChartController::rt_line
	);

    public function __construct() {

		parent::__construct();
		$this->list_control_panel_partial = PATH_APP.'/modules/shop/controllers/shop_customers_report/_reports_export_buttons.htm';
    }


	public function index() {
		$this->app_page_title     = 'Customers';
		$this->viewData['report'] = 'customers';
		$this->app_module_name    = 'Shop Report';
	}

	public function listPrepareData() {
		$obj                                            = Shop_Customer::create();
		$this->add_calculated_columns($obj);
		$this->filterApplyToModel( $obj );
		$this->applyIntervalToModel( $obj );
		$obj->where('shop_customers.deleted_at is null');

		return $obj;
	}

	public function listExtendModelObject( $model ) {
		if ( $model instanceof Shop_Customer ) {
			//$this->list_data_context = 'customers_report_data';
			$this->add_calculated_columns($model);
		}

		return $model;
	}

	public function listFormatRecordUrl($model) {
		if (!($model instanceof Shop_customer)) {
			$extension = $this->getExtension('Db_ListBehavior');
			return $extension->listGetTotalItemNumber($model);
		}

			return url('/shop/customers/preview/'.$model->id);
	}

	public function export_customers($format = null)
	{
		$this->list_name = get_class($this).'_index_list';
		$options = array();
		$options['iwork'] = $format == 'iwork';
		$this->listExportCsv('customers.csv', $options);
	}

	protected function add_calculated_columns(&$model){
		$model->define_column( 'report_orders_count', 'Interval Order Count' );
		$model->define_column( 'report_average_order_value', 'Interval Average Order Value' );

		$model->calculated_columns['report_orders_count'] = array( 'sql' => "count(DISTINCT(shop_orders.id))", 'type' => db_number );
		$model->calculated_columns['report_average_order_value'] = array( 'sql' => "ROUND(AVG(shop_orders.total * shop_orders.shop_currency_rate),2)", 'type' => db_number );

		if ( !$this->is_interval_context_orders() ) {
			$model->calculated_columns['report_orders_count'] = array( 'sql' => "SELECT COUNT(roc.id) FROM shop_orders AS roc WHERE (".$this->get_interval_field_query_string( 'roc.order_datetime' ).") AND roc.customer_id = shop_customers.id", 'type' => db_number );
			$model->calculated_columns['report_average_order_value'] = array( 'sql' => "SELECT ROUND(AVG(raov.total * raov.shop_currency_rate),2) FROM shop_orders AS raov WHERE (".$this->get_interval_field_query_string( 'raov.order_datetime' ).") AND raov.customer_id = shop_customers.id", 'type' => db_number );

		}
	}

	protected function applyIntervalToModel( $model ) {
		$start          = Phpr_DateTime::parse( $this->get_interval_start(), '%x' )->toSqlDate();
		$end            = Phpr_DateTime::parse( $this->get_interval_end(), '%x' )->toSqlDate();
		$interval_field = $this->get_interval_field();

		$model->group( 'shop_customers.id' );
		$model->join( 'shop_orders', 'shop_customers.id=shop_orders.customer_id' );

		if ( $this->is_interval_context_orders() ) {
			if (  $this->get_interval_context() == 'orders_paid' ) {
				$paidFilter = $this->getOrderPaidStatusFilter();
				$model->where( $paidFilter );
			}
		}

		$model->where( 'date(' . $interval_field . ') >= ?', $start );
		$model->where( 'date(' . $interval_field . ') <= ?', $end );


	}

	protected function get_interval_field_query_string($interval_field = null) {
		$start = Phpr_DateTime::parse( $this->get_interval_start(), '%x' )->toSqlDate();
		$end   = Phpr_DateTime::parse( $this->get_interval_end(), '%x' )->toSqlDate();

		$interval_field = $interval_field ? $interval_field : $this->get_interval_field();
		$result = " DATE(" . $interval_field . ") >= '$start' and DATE(" . $interval_field . ") <= '$end'";

		return $result;
	}

	protected function get_interval_field() {
		$interval_field = 'shop_customers.created_at';
		if ( $this->is_interval_context_orders() ) {
			$interval_field = 'shop_orders.order_datetime';
		}

		return $interval_field;
	}

	protected function get_interval_context(){
		return $this->getReportParameter( 'product_report_display_type', 'orders_placed' );
	}

	protected function is_interval_context_orders() {
		if ( strstr( $this->get_interval_context(), 'orders' ) ) {
			return true;
		}

		return false;
	}


	protected function onBeforeChartRender() {
		$chartType = $this->getChartType();

		if ( $chartType != Backend_ChartController::rt_column ) {
			return;
		}
	}

	public function refererName() {
		return 'Customers Report';
	}

	protected function getChartData(){
    	$series = array();
    	$data = array();

    	$chartType = $this->viewData['chart_type'] = $this->getChartType();
		$filterStr = $this->filterAsString();

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
					'amount' AS graph_name, 
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
						'amount' AS graph_name,
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
		$paidFilter = null;
		$intervalLimit = $this->get_interval_field_query_string();
		$filterStr     = $this->filterAsString();

		if (  $this->get_interval_context() == 'orders_paid' ) {
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ( $paidFilter ) {
				$paidFilter = 'and ' . $paidFilter;
			}
		}

		$query_str = "FROM shop_customers
					LEFT JOIN shop_orders 
					ON shop_customers.id = shop_orders.customer_id 
					WHERE $intervalLimit $filterStr $paidFilter";

		$query = "SELECT 
					ROUND(AVG(shop_orders.total * shop_orders.shop_currency_rate),2) AS average_order_value,
					COUNT(DISTINCT(shop_customers.id)) AS customers_count,
					COUNT(DISTINCT(shop_orders.id)) AS orders_count 
					$query_str";


		$this->viewData['totals_data'] = Db_DbHelper::object( $query );
		$this->renderPartial( 'chart_totals' );
	}

	protected function get_stock_name_sql() {
		return str_replace( ':grouped_name', $this->grouped_name_sql, $this->stock_name_sql );
	}
}

?>