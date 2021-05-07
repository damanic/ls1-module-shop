<?

	/**
	 * Implements product search functions
	 */
	class Shop_ProductSearch
	{
		protected static $products_sorting;
		protected static $products_sorting_direction;
		protected static $search_test_product;
		protected static $search_test_om_record;

		/**
		 * Finds products by a search term and/or other options.
		 * The <em>$options</em> array can have the following elements:
		 * <ul>
		 * <li><em>category_ids</em> - an array of category identifiers to limit the result with specific categories.</li>
		 * <li><em>manufacturer_ids</em> - an array of {@link Shop_Manufacturer manufacturer} identifiers to limit the result
		 *   with specific manufacturers.</li>
		 * <li><em>options</em> - an array of product options as a name-value list. Example: <em>array('color'=>'black')</em>.
		 *   You can use the <em>wildcard</em> character as an option value if you want to find all products with a specific option having any value.</li>
		 * <li><em>properties</em> - an array of product properties as a name-value list. Example: <em>array('paper format'=>'A4')</em>.
		 *   If your products can have multiple properties with a same name and you want to search by multiple property values, you
		 *   can specify the property value as array: <em>array('paper format'=>array('A4', 'A5'))</em> - the method will find products
		 *   with the <em>paper format</em> property having values of <em>A4</em> or <em>A5</em>. You can use the <em>wildcard</em>
		 *  character for the property value if you want to find all products with a specific property having any value.</li>
		 * <li><em>custom_groups</em> - an array of custom product groups to limit the result with specific groups. </li>
		 * <li><em>min_price</em> - minimum product price.</li>
		 * <li><em>max_price</em> - maximum product price.</li>
		 * <li><em>sorting</em> - the product sorting expression, string. The following values are supported: <em>relevance</em>,
		 *   <em>name</em>, <em>price</em>, <em>created_at</em>, <em>product_rating</em>, <em>product_rating_all</em>.
		 *   The <em>relevance</em> value is the default sorting option. The product_rating value corresponds to the approved
		 *   product rating, and the product_rating_all value corresponds to the full product rating (approved and not approved).
		 *   All options (except the relevance) support the sorting direction expression - <em>asc</em> and <em>desc</em>, so you
		 *   can use values like "price desc". You can extend the list of allowed sorting columns with the {@link shop:onGetProductSearchSortColumns} event</li>
		 * </ul>
		 * Usage example:
		 * <pre>
		 * // Create the pagination object, 10 records per page
		 * $pagination = new Phpr_Pagination(10);
		 *
		 * // Load the current page index from the URL
		 * $current_page = $this->request_param(0, 1);
		 *
		 * $query = 'laptop';
		 * $options = array();
		 * $options['properties'] = array('CPU'=>'2.33');
		 * $options['options'] = array('color'=>'black');
		 * $options['custom_groups'] = array('featured_products');
		 *
		 * $products = Shop_Product::find_products($query, $pagination, $current_page, $options);
		 * </pre>
		 * By default the find_products() method uses soft comparison in the options and properties search. This means that if you specify
		 * an option or property value <em>large</em> and if there are products which have the option value <em>large frame</em>, those
		 * product will be returned. You can enable strict search by adding the <em>exclamation</em> sign before the option or property value:
		 * <pre>
		 * $options['properties'] = array('CPU'=>'!2.33');
		 * $options['options'] = array('color'=>'!black');
		 * </pre>
		 * Please note that the strict property search is more efficient and reliable than the strict option search.
		 * The strict option search could work incorrectly if a product option value contains commas.
		 * @documentable
		 * @see shop:onRegisterProductSearchEvent
		 * @see shop:onGetProductSearchSortColumns
		 * @param string $query Specifies the search query string.
		 * @param Phpr_Pagination $pagination Specifies the pagination object.
		 * @param integer $page Specifies ф current page index (1-based).
		 * @param array $options Specifies search options.
		 * @return Db_DataCollection Returns a collection of Shop_Product objects.
		 */
		public static function find_products($query, $pagination, $page=1, $options = array())
		{
			$query = str_replace('%', '', $query);

			$words = Core_String::split_to_words($query);
			$query_presented = strlen(trim($query));

			$configuration = Shop_ConfigurationRecord::get();
			$customer_group_id = Cms_Controller::get_customer_group_id();

			$search_in_grouped_products = $configuration->search_in_grouped_products;
			$search_in_product_names = array_key_exists('search_in_product_names', $options) ? $options['search_in_product_names'] : true;

			$custom_sort_field_sets = Backend::$events->fireEvent('shop:onGetProductSearchSortColumns');
			$custom_sort_fields = array();
			$custom_select_fields = array();
			foreach ($custom_sort_field_sets as $fields)
			{
				foreach ($fields as $field)
				{
					if (preg_match('/^[0-9a-z\-_\s]+$/i', $field))
					{
						$normalized_field_name = trim(str_replace('desc', '', str_replace('asc', '', $field)));
						$custom_select_fields[] = 'shop_products.'.$normalized_field_name;
						$custom_sort_fields[] = $field;
					}
				}
			}

			if (count($custom_select_fields))
				$custom_select_fields = ', '.implode(', ', $custom_select_fields);
			else
				$custom_select_fields = null;

			$grouped_products_filter = "and grouped is null";

			$grouped_inventory_subquery = 'or exists(
				select
					*
				from
					shop_products grouped_products
				where
					grouped_products.product_id is not null
					and grouped_products.product_id=shop_products.id
					and grouped_products.enabled=1
					and not (
						grouped_products.track_inventory is not null
						and grouped_products.track_inventory=1
						and grouped_products.hide_if_out_of_stock is not null
						and grouped_products.hide_if_out_of_stock=1
						and (
							(grouped_products.stock_alert_threshold is not null
							and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold)
							or (grouped_products.stock_alert_threshold is null and grouped_products.total_in_stock=0)
						)
					)
			)';

			if ($search_in_grouped_products)
			{
				$grouped_products_filter = "and ifnull((if (grouped is null, shop_products.disable_completely, (select disable_completely from shop_products parent_list where parent_list.id=shop_products.product_id))), 0) = 0";
				$grouped_inventory_subquery = null;
			}

			$group_filter_field = "if (grouped is null, enable_customer_group_filter, (select enable_customer_group_filter from shop_products parent_list where parent_list.id=shop_products.product_id))";
			$search_visibility_field = "if (grouped is null, visibility_search, (select visibility_search from shop_products parent_list where parent_list.id=shop_products.product_id))";

			$om_records_exist_field = null;
			if ($configuration->search_in_option_matrix)
				$om_records_exist_field = 'exists(select id from shop_option_matrix_records where product_id=shop_products.id) as om_records_exist, ';

			$query_template = "
				select
					$om_records_exist_field
					shop_products.name,
					shop_products.created_at,
					shop_products.id,
					shop_products.price,
					shop_products.on_sale,
					shop_products.sale_price_or_discount,
					shop_products.tax_class_id,
					shop_products.price_rules_compiled,
					shop_products.tier_price_compiled,
					shop_products.product_rating,
					shop_products.product_rating_all,
					null as om_record_id,
					null as om_data_fields
					$custom_select_fields
				from
					shop_products
				left join shop_products_categories on %CATEGORY_FILTER%
				%TABLES%
				where
					(
						(shop_products.enabled=1 and not
							(shop_products.track_inventory is not null
								and shop_products.track_inventory=1
								and shop_products.hide_if_out_of_stock is not null
								and shop_products.hide_if_out_of_stock=1
								and (
									(shop_products.stock_alert_threshold is not null and shop_products.total_in_stock <= shop_products.stock_alert_threshold)
									or (shop_products.stock_alert_threshold is null and shop_products.total_in_stock=0)
								)
							)
						)
						$grouped_inventory_subquery
					)
					$grouped_products_filter
					and (
						ifnull($group_filter_field, 0) = 0
						or
						(
							exists(select * from shop_products_customer_groups where shop_product_id=if(grouped is null, shop_products.id, shop_products.product_id) and customer_group_id='$customer_group_id')
						)
					)
					and ifnull($search_visibility_field, 0) <> 0
					and (shop_products.disable_completely is null or shop_products.disable_completely = 0)
					and %FILTER% order by shop_products.name";

			$product_ids = array();

			if (!$search_in_grouped_products)
				$query_template = str_replace('%CATEGORY_FILTER%', 'shop_products_categories.shop_product_id=shop_products.id', $query_template);
			else
				$query_template = str_replace('%CATEGORY_FILTER%', 'shop_products_categories.shop_product_id=if(grouped is null, shop_products.id, shop_products.product_id)', $query_template);

			/*
			 * Apply categories
			 */

			$category_ids = isset($options['category_ids']) ? $options['category_ids'] : array();
			if ($category_ids)
			{
				$valid_ids = array();
				foreach ($category_ids as $category_id)
				{
					if (strlen($category_id) && preg_match('/^[0-9]+$/', $category_id))
						$valid_ids[] = $category_id;
				}

				if ($valid_ids)
				{
					$valid_ids = "('".implode("','", $valid_ids)."')";
					$query_template = Shop_Product::set_search_query_params($query_template, '%TABLES%', 'shop_products_categories.shop_category_id in '.$valid_ids.' and %FILTER%');
				}
			}

			/*
			 * Apply manufacturers
			 */

			$manufacturer_ids = isset($options['manufacturer_ids']) ? $options['manufacturer_ids'] : array();
			if ($manufacturer_ids)
			{
				$valid_ids = array();
				foreach ($manufacturer_ids as $manufacturer_id)
				{
					if (strlen($manufacturer_id) && preg_match('/^[0-9]+$/', $manufacturer_id))
						$valid_ids[] = $manufacturer_id;
				}

				if ($valid_ids)
				{
					$valid_ids = "('".implode("','", $valid_ids)."')";
					$query_template = Shop_Product::set_search_query_params($query_template, '%TABLES%', 'shop_products.manufacturer_id is not null and shop_products.manufacturer_id in '.$valid_ids.' and %FILTER%');
				}
			}

			/*
			 * Apply options
			 */

			$product_options = isset($options['options']) ? $options['options'] : array();
			if ($product_options)
			{
				$option_queries = array();
				foreach ($product_options as $name=>$values)
				{
					if (!is_array($values))
						$values = array($values);

					$product_option_queries = array();

					foreach ($values as $value)
					{
						$value = trim($value);
						if (!strlen($value))
							continue;

						if ($value == '*')
							$value = '';

						$name = Db_DbHelper::escape($name);
						$value = Db_DbHelper::escape($value);
						if (substr($value, 0, 1) != '!')
							$product_option_queries[] = "(exists(select id from shop_custom_attributes where name='".$name."' and attribute_values like '%".$value."%' and product_id=shop_products.id))";
						else
						{
							$value = substr($value, 1);
							$product_option_queries[] = "(exists(select id from shop_custom_attributes where name='".$name."' and find_in_set('".$value."', replace(attribute_values, '"."\n"."', ',')) > 0 and product_id=shop_products.id))";
						}
					}

					if($product_option_queries)
						$option_queries[] = '('.implode(' or ', $product_option_queries).')';
				}

				if ($option_queries)
				{
					$option_queries = implode(' and ', $option_queries);
					$query_template = Shop_Product::set_search_query_params($query_template, '%TABLES%', $option_queries.' and %FILTER%');
				}
			}

			/*
			 * Apply product properties
			 */

			$product_properties  = isset($options['attributes']) ? $options['attributes'] : array(); //@deprecated
			$product_properties  = isset($options['properties']) ? $options['properties'] : $product_properties;
			if ($product_properties)
			{
				$property_queries = array();
				foreach ($product_properties as $name=>$values)
				{
					if (!is_array($values))
						$values = array($values);

					$product_property_queries = array();

					foreach ($values as $value)
					{
						$value = trim($value);
						if (!strlen($value))
							continue;

						if ($value == '*')
							$value = '';

						$name = Db_DbHelper::escape($name);
						$value = Db_DbHelper::escape($value);
						if (substr($value, 0, 1) != '!')
							$product_property_queries[] = "(exists(select id from shop_product_properties where name='".$name."' and value like '%".$value."%' and product_id=shop_products.id))";
						else {
							$value = substr($value, 1);
							$product_property_queries[] = "(exists(select id from shop_product_properties where name='".$name."' and value= '".$value."' and product_id=shop_products.id))";
						}
					}

					if($product_property_queries)
						$property_queries[] = '('.implode(' or ', $product_property_queries).')';
				}

				if ($property_queries)
				{
					$property_queries = implode(' and ', $property_queries);
					$query_template = Shop_Product::set_search_query_params($query_template, '%TABLES%', $property_queries.' and %FILTER%');
				}
 			}

 			/*
			* Apply custom product groups
			*/

			$custom_groups = isset($options['custom_groups']) ? $options['custom_groups'] : array();
			if($custom_groups)
			{
				$valid_groups = array();
				foreach($custom_groups as $custom_group)
				{
					if(strlen($custom_group))
						$valid_groups[] = Db_DbHelper::escape($custom_group);
				}
				if(count($valid_groups))
				{
					$custom_groups = "('".implode("','", $valid_groups)."')";
					$custom_group_filter = " shop_products.id in (select shop_product_id from shop_products_customgroups inner join shop_custom_group on (shop_products_customgroups.shop_custom_group_id = shop_custom_group.id) where code in ".$custom_groups.") ";
					$query_template = Shop_Product::set_search_query_params($query_template, '%TABLES%', $custom_group_filter.' and %FILTER%');
				}
			}

			/*
			 * Apply third-party search functions
			 */

			$search_events = Backend::$events->fireEvent('shop:onRegisterProductSearchEvent', $options, $query);

			if ($search_events)
			{
				foreach ($search_events as $event)
				{
					if ($event)
					{
						$query_template_update = Backend::$events->fireEvent($event, $options, $query_template, $query);
						if ($query_template_update)
						{
							foreach ($query_template_update as $template_update)
							{
								if ($template_update)
									$query_template = $template_update;
							}
						}
					}
				}
			}

			/*
			 * Search in product names
			 */

			if ($search_in_product_names)
			{
				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'shop_products.name', 2)));
				foreach ($records as $record)
					$product_ids[$record->id] = $record;
			}

			/*
			 * Search in short descriptions
			 */

			if ($configuration->search_in_short_descriptions && $query_presented)
			{
				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'short_description', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in long descriptions
			 */

			if ($configuration->search_in_long_descriptions && $query_presented)
			{
				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'pt_description', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in keywords
			 */

			if ($configuration->search_in_keywords && $query_presented)
			{
				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'meta_keywords', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in categories
			 */

			if ($configuration->search_in_categories && $query_presented)
			{
				$category_query_template = 'select id from shop_categories where %s and (category_is_hidden is null or category_is_hidden=0) order by name';
				$category_records = Db_DbHelper::objectArray(sprintf($category_query_template, Db_DbHelper::formatSearchQuery($query, 'name', 2)));

				foreach ($category_records as $category)
				{
					$product_records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, 'shop_products_categories.shop_category_id='.$category->id));

					foreach ($product_records as $record)
					{
						if (array_key_exists($record->id, $product_ids))
							continue;

						$product_ids[$record->id] = $record;
					}
				}
			}

			/*
			 * Search in manufacturers
			 */

			if ($configuration->search_in_manufacturers && $query_presented)
			{
				$manufacturer_join = 'left join shop_manufacturers on shop_manufacturers.id = shop_products.manufacturer_id';
				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, $manufacturer_join, Db_DbHelper::formatSearchQuery($query, 'shop_manufacturers.name', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in product SKU
			 */

			if ($configuration->search_in_sku && $query_presented)
			{
				$filter = "(%s or (exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id and %s)))";
				$filter = sprintf($filter,
					Db_DbHelper::formatSearchQuery($query, 'shop_products.sku', 2),
					Db_DbHelper::formatSearchQuery($query, 'grouped_products.sku', 2)
				);

				$records = Db_DbHelper::objectArray(Shop_Product::set_search_query_params($query_template, null, $filter));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Apply Option Matrix search
			 */

			$products = $product_ids;

			if ($configuration->search_in_option_matrix)
				self::apply_om_search($products, $options);

			/*
			 * $products contains a list of found products. Now we can apply price range filter
			 * and sort the list.
			 */

			/*
			 * Apply price range filter
			 */

			$min_price = isset($options['min_price']) ? trim($options['min_price']) : null;
			$max_price = isset($options['max_price']) ? trim($options['max_price']) : null;

			if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $min_price))
				$min_price = null;

			if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $max_price))
				$max_price = null;


			if (strlen($min_price) || strlen($max_price))
			{
				$product_ids = array();
				foreach ($products as $product)
					$product_ids[$product->id] = $product;

				/*
				 * Load grouped products
				 */

				if (count($products))
				{
					$grouped_products = Db_DbHelper::objectArray("
						select
							grouped_products.product_id,
							grouped_products.id,
							grouped_products.price,
							grouped_products.on_sale,
							grouped_products.sale_price_or_discount,
							grouped_products.price_rules_compiled,
							grouped_products.tier_price_compiled,
							grouped_products.tax_class_id,
							null as om_data_options,
							null as om_data_fields
							$custom_select_fields
						from
							shop_products grouped_products
						where
							grouped_products.product_id is not null
							and grouped_products.product_id in (:parent_product_ids)
							and grouped_products.enabled=1
							and not (
								grouped_products.track_inventory is not null
								and grouped_products.track_inventory=1
								and grouped_products.hide_if_out_of_stock is not null
								and grouped_products.hide_if_out_of_stock=1
								and (
									(
										grouped_products.stock_alert_threshold is not null
										and grouped_products.total_in_stock <= grouped_products.stock_alert_threshold
									) or (
										grouped_products.stock_alert_threshold is null
										and grouped_products.total_in_stock=0
										)
									)
							)", array(
						'parent_product_ids'=>array_keys($product_ids)
					));
				} else
					$grouped_products = array();

				$grouped_products_sorted = array();
				foreach ($grouped_products as $grouped_product)
				{
					if (!array_key_exists($grouped_product->product_id, $grouped_products_sorted))
						$grouped_products_sorted[$grouped_product->product_id] = array();

					$grouped_products_sorted[$grouped_product->product_id][$grouped_product->id] = $grouped_product;
				}

				$test_product = Shop_Product::create();
				$test_om_record = Shop_OptionMatrixRecord::create();
				$filtered_products = array();
				foreach ($products as $product)
				{
					if (Shop_Product::check_price_range($test_product, $product, $min_price, $max_price, $test_om_record))
						$filtered_products[] = $product;
					else
					{
						if (array_key_exists($product->id, $grouped_products_sorted))
						{
							foreach ($grouped_products_sorted[$product->id] as $grouped_product)
							{
								if (Shop_Product::check_price_range($test_product, $grouped_product, $min_price, $max_price, $test_om_record))
								{
									$filtered_products[] = $product;
									continue;
								}
							}
						}
					}
				}

				$products = $filtered_products;
			}

			/*
			 * Apply product sorting
			 */

			$sorting = array_key_exists('sorting', $options) ? trim($options['sorting']) : 'relevance';

			if (!$sorting)
				$sorting = 'relevance';

			$allowed_sorting_columns = array('relevance', 'name', 'price', 'created_at', 'product_rating', 'product_rating_all');

			foreach ($custom_sort_fields as $custom_sort_field)
				$allowed_sorting_columns[] = $custom_sort_field;

			$normalized_search_expr = mb_strtolower($sorting);
			$normalized_search_expr = trim(str_replace('desc', '', str_replace('asc', '', $normalized_search_expr)));

			if (!in_array($normalized_search_expr, $allowed_sorting_columns))
				$sorting = 'relevance';

			self::$products_sorting = $normalized_search_expr;
			self::$products_sorting_direction = strpos($sorting, 'desc') === false ? 1 : -1;
			self::$search_test_product = Shop_Product::create();
			self::$search_test_om_record = Shop_OptionMatrixRecord::create();

			if ($sorting != 'relevance')
				uasort($products, array('Shop_ProductSearch', 'sort_search_result'));

			/*
			 * Paginate and return the data collection
			 */

			$pagination->setRowCount(count($products));
			$pagination->setCurrentPageIndex($page-1);

			$products = array_slice($products, $pagination->getFirstPageRowIndex(), $pagination->getPageSize(), true);

			$result_array = array();
			if (count($products))
			{
				$result = array();
				$loaded_products = array();
				foreach ($products as $product_data)
				{
					if (array_key_exists($product_data->id, $loaded_products))
						$product = clone $loaded_products[$product_data->id];
					else
					{
						$product = Shop_Product::create()->where('shop_products.id=?', $product_data->id)->find();
						$loaded_products[$product_data->id] = $product;
					}

					if ($product_data->om_record_id)
						$product->set_om_options(Shop_OptionMatrixRecord::create()->find($product_data->om_record_id));

					$result[] = $product;
				}

				return new Db_DataCollection($result);
			}

 			return new Db_DataCollection(array());
		}

		/**
		 * Extends search result with Option Matrix products
		 */
		protected static function apply_om_search(&$products, $options)
		{
			/*
			 * Filter products with OM records
			 */

			$products_with_om = array();

			foreach ($products as $product_id=>$product)
			{
				if ($product->om_records_exist)
					$products_with_om[$product_id] = $product;
			}

			foreach ($products_with_om as $product_id=>$product)
				unset($products[$product_id]);

			/*
			 * Process only products which have Option Matrix records.
			 */

			if ($products_with_om)
			{
				/*
				 * Remove products which have Option Matrix records, but all of records
				 * are disabled.
				 */

				$disabled_product_ids = Db_DbHelper::scalarArray('
					select
						id
					from
						shop_products
					where
						not exists (select id from shop_option_matrix_records where (disabled is null or disabled=0) and product_id=shop_products.id)
						and shop_products.id in (:product_ids)
				', array('product_ids'=>array(array_keys($products_with_om))));

				foreach ($disabled_product_ids as $id)
					if (array_key_exists($id, $products_with_om))
						unset($products_with_om[$id]);

				/*
				 * Load Option Matrix records which satisfy the search parameters.
				 */

				$product_options = isset($options['options']) ? $options['options'] : array();
				$option_filter = null;
				$options_queries = array();

				foreach ($product_options as $name=>$value)
				{
					$value = trim($value);
					if (!strlen($value))
						continue;

					if ($value == '*')
						$value = '';

					$name = Db_DbHelper::escape($name);
					$value = Db_DbHelper::escape($value);
					$strict_condition = mb_substr($value, 0, 1) == '!';
					$condition = $strict_condition ? "option_value='".mb_substr($value, 1)."'" : "option_value like '%".$value."%'";

					$options_queries[] = "(exists(select
							shop_custom_attributes.id
						from
							shop_option_matrix_options,
							shop_custom_attributes
						where
							shop_custom_attributes.id=shop_option_matrix_options.option_id
							and shop_option_matrix_options.matrix_record_id=shop_option_matrix_records.id
							and name='".$name."'
							and $condition
						)
					)";
				}

				if ($options_queries)
					$option_filter = ' and '.implode(' and ', $options_queries);

				$om_records = Db_DbHelper::objectArray("
					select
						product_id,
						base_price,
						on_sale,
						price_rules_compiled,
						tier_price_compiled,
						sale_price_or_discount,
						sku,
						id
					from
						shop_option_matrix_records
					where
						product_id in (:product_id)
						and (disabled is null or disabled=0)
						$option_filter
				", array('product_id'=>array(array_keys($products_with_om))));

				/*
				 * Populate the list of found products with OM records
				 */

				foreach ($om_records as $om_record)
				{
					if (!array_key_exists($om_record->product_id, $products_with_om))
						continue;

					$product = $products_with_om[$om_record->product_id];
					$product_data = clone $product;
					$product_data->om_data_fields = $om_record;
					$product_data->om_record_id = $om_record->id;
					$products[] = $product_data;
				}
			}
		}

		/**
		 * Sorts products. This method is used internally.
		 */
		public static function sort_search_result($product_1, $product_2)
		{
			if (self::$products_sorting != 'price')
			{
				if (self::$products_sorting == 'name')
					return strcmp(mb_strtolower($product_1->name), mb_strtolower($product_2->name))*self::$products_sorting_direction;

				$products_sorting = self::$products_sorting;

				if (!strlen($product_1->$products_sorting) && !strlen($product_2->$products_sorting))
					return strcmp($product_1->name, $product_2->name);

				if ($product_1->$products_sorting == $product_2->$products_sorting)
					return 0;

				if ($product_1->$products_sorting > $product_2->$products_sorting)
					return 1*self::$products_sorting_direction;

				return -1*self::$products_sorting_direction;
			} else
			{
				$product_1_price = Shop_Product::eval_static_product_price(self::$search_test_product, $product_1);
				if ($product_1->om_data_fields)
					$product_1_price = Shop_OptionMatrixRecord::get_sale_price_static(self::$search_test_product, self::$search_test_om_record, $product_1->om_data_fields);

				$product_2_price = Shop_Product::eval_static_product_price(self::$search_test_product, $product_2);
				if ($product_2->om_data_fields)
					$product_2_price = Shop_OptionMatrixRecord::get_sale_price_static(self::$search_test_product, self::$search_test_om_record, $product_2->om_data_fields);

				if ($product_1_price == $product_2_price)
					return 0;

				if ($product_1_price > $product_2_price)
					return 1*self::$products_sorting_direction;

				return -1*self::$products_sorting_direction;
			}
		}
	}
?>