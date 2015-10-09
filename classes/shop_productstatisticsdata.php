<?

	/**
	 * Returns product performance data for the Product Performance area on the Product Preview page
	 */
	class Shop_ProductStatisticsData
	{
		protected static $grouped_ids_cache = array();
		
		/**
		 * Returns the number of product items sold and total amount, including grouped products.
		 * @var integer $product_id Specifies the product identifier.
		 * @return mixed Returns an object with two fields - amount and quantity
		 */
		public static function sales_summary($product_id)
		{
			$grouped_ids = self::get_grouped_ids($product_id);

			$result = Db_DbHelper::object('
				select 
					sum(quantity) as quantity, 
					sum(quantity*(price+shop_order_items.extras_price-shop_order_items.discount)) as amount 
				from 
					shop_order_items,
					shop_orders
				where 
					shop_orders.id=shop_order_items.shop_order_id
					and shop_orders.deleted_at is null
					and shop_product_id in (:product_id)',
				array('product_id'=>$grouped_ids)
			);
			
			if (!$result->quantity)
				$result->quantity = 0;

			if (!$result->amount)
				$result->amount = 0;
			
			return $result;
		}
		
		/**
		 * Returns chart data for the product sales report.
		 * @var integer $product_id Specifies the product identifier.
		 * @return mixed Returns an object with two fields- chart_data and chart_series
		 */
		public static function sales_chart_data($product_id)
		{
			$result = array(
				'chart_data'=>array(),
				'chart_series'=>array()
			);
			
			$result = (object)$result;
			
			$grouped_ids = self::get_grouped_ids($product_id);
			
			$first_date = Db_DbHelper::scalar('
				select 
					order_datetime 
				from 
					shop_orders, 
					shop_order_items
				where 
					shop_orders.id=shop_order_items.shop_order_id
					and shop_product_id in (:product_id)
				order by order_datetime asc
				limit 0,1',
				array('product_id'=>$grouped_ids)
			);
			
			if (!$first_date)
				return $result;
			
			$start_date = Phpr_Date::firstMonthDate(Phpr_DateTime::parse($first_date))->getDate();
			$end_date = Phpr_Date::lastMonthDate(Phpr_Date::userDate(Phpr_DateTime::gmtNow()))->getDate();

			$intervalLimit = " report_date >= '$start_date' and report_date <= '$end_date'";

			$query = "
				select
					'amount' as graph_code,
					'amount' as graph_name,
					report_date as series_id,
					report_date as series_value,
					sum(shop_order_items.quantity*(shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)) as record_value,
					sum(shop_order_items.quantity) as items_sold
				from 
					report_dates
				left join shop_orders on report_date = date(shop_orders.order_datetime)
				left join shop_order_items on shop_order_items.shop_order_id = shop_orders.id 
				left join shop_products on shop_order_items.shop_product_id=shop_products.id

				where
					(
						(
							shop_products.id in (:product_ids)
							and shop_orders.deleted_at is null
						) 
							or shop_orders.id is null
					)
					and report_date >= :start_date
					and report_date <= :end_date

				group by report_date
				order by report_date
			";

			$series_query = "
				select
					report_date as series_id,
					report_date as series_value
				from report_dates
				where 
					report_date >= :start_date
					and report_date <= :end_date
				order by report_date
			";

			$bind = array(
				'product_ids'=>$grouped_ids,
				'start_date'=>$start_date,
				'end_date'=>$end_date
			);

			$result->chart_data = Db_DbHelper::objectArray($query, $bind);
			$result->chart_series = Db_DbHelper::objectArray($series_query, $bind);

			return $result;
		}
		
		/**
		 * Returns chart data for the grouped products sales report.
		 * @var integer $product_id Specifies the master product identifier.
		 * @return array Returns the chart data as array
		 */
		public static function grouped_chart_data($product_id)
		{
			$grouped_ids = self::get_grouped_ids($product_id);
			
			$query = "
				select 
					shop_products.id as graph_code, 
					'serie' as series_id, 
					'serie' as series_value, 
					shop_products.grouped_option_desc as graph_name, 
					shop_products.sku as sku,
					shop_products.name as name,
					sum(shop_order_items.quantity*(shop_order_items.price+shop_order_items.extras_price-shop_order_items.discount)) as record_value,
					sum(shop_order_items.quantity) as items_sold
				from 
					shop_order_items,
					shop_orders,
					shop_products
				where 
					shop_products.id = shop_order_items.shop_product_id
					and shop_orders.id=shop_order_items.shop_order_id
					and shop_product_id in (:product_id)
					and shop_orders.deleted_at is null
				group by shop_products.id
				order by shop_products.id";
				
			return Db_DbHelper::objectArray($query, array('product_id'=>$grouped_ids));
		}
		
		/**
		 * Returns a list of identifiers of grouped products belonging to a product with the specified identifier
		 * @var integer $product_id Specifies the product identifier.
		 * @return array Returns an array of product identifiers
		 */
		protected static function get_grouped_ids($product_id)
		{
			if (array_key_exists($product_id, self::$grouped_ids_cache))
				return self::$grouped_ids_cache[$product_id];
			
			$grouped_ids = Db_DbHelper::scalarArray('
				select id from shop_products where product_id is not null and product_id=:product_id', 
				array('product_id'=>$product_id)
			);

			$grouped_ids[] = $product_id;
			
			return self::$grouped_ids_cache[$product_id] = $grouped_ids;
		}
	}

?>