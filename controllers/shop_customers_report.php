<?php

/*
 * @todo orders_placed + orders_paid context is slow
 */
class Shop_Customers_Report extends Shop_GenericReport {

	public $list_model_class = 'Shop_Customer';
	public $list_no_data_message = 'No customers found';
	public $list_data_context = null;
	public $list_no_setup_link = false;
	public $list_control_panel_partial = null;



	protected $chart_types = array(
		Backend_ChartController::rt_line
	);

	protected $display_objects = array(
		'account_created'  => 'New customers',
		'orders_placed'    => 'Customers who placed orders',
		'orders_paid'      => 'Customers with paid orders',
		'customers_no_paid_orders'   => 'Customers with no paid orders',
		'customers_no_placed_orders' => 'Customers with no placed orders',
	);

	public $filter_filters = array(
		'customers_billing_country'  => array( 'name' => 'Customers billing country', 'class_name' => 'Shop_CustomerBillingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Customers who do not have these countries saved as their current billing address will be hidden.', 'added_list_title' => 'Added Countries' ),
		'customers_shipping_country'  => array( 'name' => 'Customers shipping country', 'class_name' => 'Shop_CustomerShippingCountryFilter', 'prompt' => 'Please choose countries you want to include to the list. Customers who do not have these countries saved as their current shipping address will be hidden.', 'added_list_title' => 'Added Countries' ),
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
		$this->applyIntervalToModel( $obj );
		$this->filterApplyToModel( $obj );
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

		$model->group( 'shop_customers.id' );
		$model->join( 'shop_orders', 'shop_customers.id=shop_orders.customer_id' );

    	$customer_ids = null;
		if( $this->get_interval_context() == 'customers_no_placed_orders'){
			$customer_ids = $this->get_customer_ids_with_no_orders_in_interval(false);
		}
		if( $this->get_interval_context() == 'customers_no_paid_orders'){
			$customer_ids = $this->get_customer_ids_with_no_orders_in_interval(true);
		}

		if($customer_ids){
			$model->where('shop_customers.id IN (?)', array($customer_ids));
		} else {

			$start          = Phpr_DateTime::parse( $this->get_interval_start(), '%x' )->toSqlDate();
			$end            = Phpr_DateTime::parse( $this->get_interval_end(), '%x' )->toSqlDate();
			$interval_field = $this->get_interval_field();

			if ( $this->is_interval_context_orders() ) {
				if ( $this->get_interval_context() == 'orders_paid' ) {
					$paidFilter = $this->getOrderPaidStatusFilter();
					$model->where( $paidFilter );
				}
			}
			$model->where( 'date(' . $interval_field . ') >= ?', $start );
			$model->where( 'date(' . $interval_field . ') <= ?', $end );
		}
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
		return $this->getReportParameter( 'customers_report_display_type', 'account_created' );
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

    	$contexts_not_supported = array(
    		'customers_no_paid_orders',
			'customers_no_placed_orders'
		);
    	if(in_array($this->get_interval_context(), $contexts_not_supported)){
    		return false;
		}

    	$chartType = $this->viewData['chart_type'] = $this->getChartType();
		$filterStr = null;
		$paidFilter = null;
		$whereFilter = null;


		$graph_name = 'New Customers';
		$report_table = 'shop_customers';
		$report_join = "left join shop_customers on report_date = DATE(shop_customers.created_at)";

		if($this->is_interval_context_orders()){
			$graph_name = 'Customers';
			$report_table = 'shop_orders';
			$report_join = "left join shop_orders on report_date = shop_orders.order_date
							left join shop_customers on shop_customers.id=shop_orders.customer_id";

			if($this->get_interval_context() == 'orders_paid'){
				$paidFilter = 'AND '.$this->getOrderPaidStatusFilter();
			}
		}

		$filterStr = $this->filterAsString();



		if ( $chartType == Backend_ChartController::rt_line ) {

			$intervalLimit    = $this->intervalQueryStr();
			$seriesIdField    = $this->timeSeriesIdField();
			$seriesValueField = $this->timeSeriesValueField();

			// if ($paidFilter)
			// 	$paidFilter = 'and '.$paidFilter;

			$data_query = "
					select
						'customer' AS graph_code, 
						'{$graph_name}' AS graph_name,
						{$seriesIdField} as series_id,
						{$seriesValueField} as series_value,
						COUNT(DISTINCT(shop_customers.id)) as record_value
					from 
						report_dates
					$report_join

					where 
						(
							($report_table.deleted_at is null
							$paidFilter
							$filterStr
							$whereFilter)
						)

					and $intervalLimit
					group by {$seriesIdField}, graph_code
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

			$series = Db_DbHelper::objectArray( $series_query );
			$bind                         = array();
			$data = Db_DbHelper::objectArray( $data_query, $bind );
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

	protected function renderReportTotals() {

    	$totals_data = (object) array(
    		'average_order_value' => 0,
			'customers_count' => 0,
			'orders_count' => 0
		);

		$order_total_contexts = array(
			'orders_placed',
			'orders_paid'
		);

		$paidFilter    = null;
		$intervalLimit = $this->get_interval_field_query_string();
		$filterStr     = $this->filterAsString();

		if(in_array($this->get_interval_context(), $order_total_contexts)) {


			if ( $this->get_interval_context() == 'orders_paid' ) {
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

			$totals_data = Db_DbHelper::object( $query );
		} else {

				$customer_ids = array();
				$customer_id_filter = 'shop_customers.id IN (?)';
				if( $this->get_interval_context() == 'customers_no_placed_orders'){
					$customer_ids = $this->get_customer_ids_with_no_orders_in_interval(false);
				}
				if( $this->get_interval_context() == 'customers_no_paid_orders'){
					$customer_ids = $this->get_customer_ids_with_no_orders_in_interval(true);
				}

				$intervalLimit = $customer_ids ? $customer_id_filter : $intervalLimit;
				$query = "SELECT COUNT(DISTINCT(shop_customers.id)) AS customers_count 
					FROM shop_customers
					WHERE $intervalLimit $filterStr";

				$result = Db_DbHelper::object( $query, array($customer_ids) );
				$totals_data->customers_count = $result ? $result->customers_count : 0;


		}

		$this->viewData['totals_data'] = $totals_data;
		$this->renderPartial( 'chart_totals' );
	}


	protected function getOrderPaidStatusFilter($paid=true) {

		if ( !$this->paid_order_status_id ) {
			$this->paid_order_status_id = Shop_OrderStatus::get_status_paid()->id;
		}

		if ( !is_numeric( $this->paid_order_status_id ) ) {
			return null;
		}

		$qualifier = $paid ? 'EXISTS' : 'NOT EXISTS';

		return "($qualifier (SELECT id FROM shop_order_status_log_records WHERE order_id = shop_orders.id AND status_id=" . $this->paid_order_status_id . "))";
	}

	protected function get_customer_ids_with_no_orders_in_interval($paid=false){
    	$paid_filter = $paid ? 'AND '.$this->getOrderPaidStatusFilter() : null;
    	$interval_limit = $this->get_interval_field_query_string('shop_orders.order_datetime');
    	$sql = "SELECT DISTINCT(shop_customers.id) 
				FROM shop_customers
				WHERE  shop_customers.id NOT IN(
					SELECT shop_orders.customer_id 
					FROM shop_orders 
					WHERE 
					$interval_limit
					$paid_filter
				)";
    	return Db_DbHelper::scalarArray($sql);
	}



}

?>