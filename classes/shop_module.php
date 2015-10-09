<?php

	class Shop_Module extends Core_ModuleBase
	{
		private $shippingTypes = null;
		private $paymentTypes = null;
		private $currencyConverters = null;
		
		private static $partials = null;
		private static $catalog_version_update = false;

		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Shop",
				"LemonStand shopping-cart features",
				"LemonStand eCommerce Inc." );
		}

		/**
		 * Returns a list of the module back-end GUI tabs.
		 * @param Backend_TabCollection $tabCollection A tab collection object to populate.
		 * @return mixed
		 */
		public function listTabs($tabCollection)
		{
			$user = Phpr::$security->getUser();
			$tabs = array(
				'categories'=>array('categories', 'Categories', 'manage_categories'),
				'products'=>array('products', 'Products', 'manage_products'),
				'orders'=>array('orders', 'Orders', 'manage_orders_and_customers'),
				'customers'=>array('customers', 'Customers', 'manage_orders_and_customers'),
				'taxes'=>array('tax_classes', 'Tax Classes', 'manage_shop_settings'),
				'shipping'=>array('shipping', 'Shipping Options', 'manage_shop_settings'),
				'payment'=>array('payment', 'Payment Methods', 'manage_shop_settings'),
				'catalog_rules'=>array('catalog_rules', 'Catalog Price Rules', 'manage_discounts'),
				'cart_rules'=>array('cart_rules', 'Discounts', 'manage_discounts'),
				'export'=>array('export', 'Export Products', 'manage_products'),
			);

			$first_tab = null;
			foreach ($tabs as $tab_id=>$tab_info)
			{
				if (($tabs[$tab_id][3] = $user->get_permission('shop', $tab_info[2])) && !$first_tab)
					$first_tab = $tab_info[0];
			}

			if ($first_tab)
			{
				$tab = $tabCollection->tab('shop', 'Shop', $first_tab, 30);
				foreach ($tabs as $tab_id=>$tab_info)
				{
					if ($tab_info[3])
						$tab->addSecondLevel($tab_id, $tab_info[1], $tab_info[0]);
				}
			}
		}
		
		/**
		 * Returns notifications to be displayed in the main menu.
		 * @return array Returns an array of notifications in the following format:
		 * array(
		 *    array(
		 *      'id'=>'new-tickets',
		 *      'closable'=>false,
		 *      'text'=>'10 new support tickets',
		 *      'icon'=>'resources/images/notification.png',
		 *      'link'=>'/support/tickets'
		 *    )
		 * ).
		 * The 'link', 'id' and 'closable' keys are optional, but id should be specified if closable is true.
		 * Use the url() function to create values for the 'link' value.
		 * The icon should be a PNG image of size 16x16. Icon path should be specified relative to the module
		 * root directory.
		 */
		public function listMenuNotifications()
		{
			$user = Phpr::$security->getUser();
			if (!$user->get_permission('shop', 'manage_orders_and_customers'))
				return array();

			return array(
				array(
					'text'=>'Subscribe to orders RSS feed',
					'icon'=>'resources/images/menu_rss_icon.png',
					'link'=>url('shop/orders/rss'),
					'closable'=>true,
					'id'=>'menu-rss-link'
				)
			);
		}
		
		public function subscribeEvents()
		{
			Backend::$events->addEvent('cms:onDeletePage', $this, 'allowDeletePage');
			Backend::$events->addEvent('onFrontEndLogin', $this, 'frontEndLogin');
			Backend::$events->addEvent('onDeleteEmailTemplate', $this, 'checkTemplateDeletion');
			Backend::$events->addEvent('onLogin', $this, 'backendLogin');
			Backend::$events->addEvent('cms:onRegisterTwigExtension', $this, 'register_twig_extension');
		}
		
		public function register_twig_extension($environment)
		{
			$extension = Shop_TwigExtension::create();
			$environment->addExtension($extension);
			
			$functions = $extension->getFunctions();
			foreach ($functions as $function)
				$environment->addFunction($function, new Twig_Function_Method($extension, $function, array('is_safe' => array('html'))));
		}
		
		public function register_access_points()
		{
			return array(
				'ls_shop_apply_catalog_rules'=>'apply_catalog_rules',
				'ls_shop_process_catalog_rules_batch'=>'process_catalog_rules_batch',
				'ls_shop_process_catalog_rules_om_batch'=>'process_catalog_rules_om_batch',
				'ls_shop_auto_billing'=>'process_auto_billing'
			);
		}
		
		public function apply_catalog_rules()
		{
			if (Core_CronManager::access_allowed())
			{
				$processed_products = Shop_CatalogPriceRule::apply_price_rules();
				echo 'Price rules have been successfully applied to '.$processed_products.' product(s).';
			}
		}
		
		public function process_catalog_rules_batch()
		{
			try
			{
				Shop_CatalogPriceRule::process_products_batch(post('ids'));
				echo 'SUCCESS';
			} catch (exception $ex)
			{
				echo $ex->getMessage();
			}
		}
		
		public function process_catalog_rules_om_batch()
		{
			try
			{
				Shop_CatalogPriceRule::process_product_om_batch(post('ids'));
				echo 'SUCCESS';
			} catch (exception $ex)
			{
				echo $ex->getMessage();
			}
		}
		
		public function process_auto_billing()
		{
			if (Core_CronManager::access_allowed())
			{
				$result = Shop_AutoBilling::create()->process();
				echo Shop_AutoBilling::format_result($result);
			}
		}
		
		public function listShippingTypes()
		{
			if ($this->shippingTypes !== null)
				return $this->shippingTypes;

			$typesPath = PATH_APP."/modules/shop/shipping_types";
			$iterator = new DirectoryIterator($typesPath);
			foreach ($iterator as $file)
			{
				if (!$file->isDir() && preg_match('/^shop_[^\.]*\.php$/i', $file->getFilename()))
					require_once($typesPath.'/'.$file->getFilename());
			}
			
			$modules = Core_ModuleManager::listModules();
			foreach ($modules as $module_id=>$module_info)
			{
				$class_path = PATH_APP."/modules/".$module_id."/shipping_types";
				if (file_exists($class_path))
				{
					$iterator = new DirectoryIterator($class_path);

					foreach ($iterator as $file)
					{

						if (!$file->isDir() && preg_match('/^'.$module_id.'_[^\.]*\.php$/i', $file->getFilename()))
							require_once($class_path.'/'.$file->getFilename());
					}
				}
			}

			$classes = get_declared_classes();
			$this->shippingTypes = array();
			foreach ($classes as $class)
			{
				if (preg_match('/Shipping$/i', $class) && get_parent_class($class) == 'Shop_ShippingType')
					$this->shippingTypes[] = $class;
			}
			
			return $this->shippingTypes;
		}
		
		public function listCurrencyConverters()
		{
			if ($this->currencyConverters !== null)
				return $this->currencyConverters;
				
			$typesPath = PATH_APP."/modules/shop/currency_converters";
			$iterator = new DirectoryIterator($typesPath);
			foreach ($iterator as $file)
			{
				if (!$file->isDir() && preg_match('/^shop_[^\.]*\.php$/i', $file->getFilename()))
					require_once($typesPath.'/'.$file->getFilename());
			}

			$classes = get_declared_classes();
			$this->currencyConverters = array();
			
			foreach ($classes as $class)
			{
				if (preg_match('/Converter$/i', $class) && get_parent_class($class) == 'Shop_CurrencyConverterBase')
					$this->currencyConverters[] = $class;
			}
			
			return $this->currencyConverters;
		}

		public function listPaymentTypes()
		{
			if ($this->paymentTypes !== null)
				return $this->paymentTypes;

			$typesPath = PATH_APP."/modules/shop/payment_types";
			$iterator = new DirectoryIterator($typesPath);
			foreach ($iterator as $file)
			{
				$file_name = $file->getFilename();
				$file_path = $typesPath.'/'.$file_name;
				
				if (is_dir($file_path))
					continue;
					
				if (substr($file_name, 0, 5) == 'shop_' && substr($file_name, -4) == '.php')
					require_once($typesPath.'/'.$file->getFilename());
			}
			
			$modules = Core_ModuleManager::listModules();
			foreach ($modules as $module_id=>$module_info)
			{
				$class_path = PATH_APP."/modules/".$module_id."/payment_types";
				if (file_exists($class_path))
				{
					$iterator = new DirectoryIterator($class_path);

					foreach ($iterator as $file)
					{

						if (!$file->isDir() && preg_match('/^'.$module_id.'_[^\.]*\.php$/i', $file->getFilename()))
							require_once($class_path.'/'.$file->getFilename());
					}
				}
			}

			$classes = get_declared_classes();
			$this->paymentTypes = array();
			foreach ($classes as $class)
			{
				if (preg_match('/_Payment$/i', $class) && get_parent_class($class) == 'Shop_PaymentType')
					$this->paymentTypes[] = $class;
			}
			
			return $this->paymentTypes;
		}
		
		public function allowDeletePage($page)
		{
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from shop_categories where page_id=:id', 
				array('id'=>$page->id)
			);
			
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete page: it is used as a category landing page.");
				
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from shop_products where page_id=:id and (grouped is null or grouped <> 1)', 
				array('id'=>$page->id)
			);
			
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete page: it is used as a product landing page.");

			$isInUse = Db_DbHelper::scalar(
				'select count(*) from shop_payment_methods where receipt_page_id=:id', 
				array('id'=>$page->id)
			);

			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete page: it is used as a payment method thank you page.");
				
			Shop_PaymentMethod::page_deletion_check($page);
		}
		
		public function checkTemplateDeletion($template)
		{
			$shop_templates = array('shop:registration_confirmation', 'shop:password_reset', 'shop:new_order_internal', 'shop:order_status_update_internal');
			
			if (in_array($template->code, $shop_templates))
				throw new Phpr_ApplicationException("This template is used by the Shop module.");
				
			$status = Shop_OrderStatus::create()->find_by_customer_message_template_id($template->id);
			if ($status)
			{
				$statusName = h($status->name);
				throw new Phpr_ApplicationException("This template cannot be deleted because it is used in {$statusName} order status.");
			}
		}

		public function frontEndLogin()
		{
			Shop_Cart::move_cart();
		}

		public function backendLogin()
		{
			Shop_CurrencyRateRecord::delete_old_records();
		}

		public function listSettingsItems()
		{
			$result = array(
				array(
					'icon'=>'/modules/shop/resources/images/currency_settings.png', 
					'title'=>'Currency', 
					'url'=>'/shop/settings/currency',
					'description'=>'Configure the store currency. Set currency formatting parameters and ISO code.',
					'sort_id'=>50,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/countries_settings.png', 
					'title'=>'Countries and States', 
					'url'=>'/shop/settings/countries',
					'description'=>'Setup a list of countries and states you cater to. Set ISO codes for countries and states.',
					'sort_id'=>70,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/statuses_settings.png', 
					'title'=>'Order Route', 
					'url'=>'/shop/statuses',
					'description'=>'Configure possible order statuses, status colors, transitions and email notification rules.',
					'sort_id'=>100,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/roles_settings.png', 
					'title'=>'Roles', 
					'url'=>'/shop/roles',
					'description'=>'Configure user roles. Specify what roles can create orders and receive "Out of stock" notifications.',
					'sort_id'=>90,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/shipping_settings.png', 
					'title'=>'Shipping Configuration', 
					'url'=>'/shop/shipping_settings',
					'description'=>'Specify a shipping origin and default location, weight and dimension units.',
					'sort_id'=>80,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/currency_converter_settings.png', 
					'title'=>'Currency Converter', 
					'url'=>'/shop/currency_converter_settings',
					'description'=>'Select and configure a currency converter used by LemonStand.',
					'sort_id'=>60,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/company_info.png', 
					'title'=>'Company Information and Settings', 
					'url'=>'/shop/company_info',
					'description'=>'Set merchant company name, address and logo. Configure invoices and packing slips.',
					'sort_id'=>110,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/shop_configuration.png', 
					'title'=>'eCommerce Settings', 
					'url'=>'/shop/configuration',
					'description'=>'Define the shopping cart behavior and configure other eCommerce parameters.',
					'sort_id'=>120,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/set_order_numbering.png', 
					'title'=>'Set Order Numbering', 
					'url'=>'/shop/order_numbering',
					'description'=>'Set or update the start order number.',
					'sort_id'=>130,
					'section'=>'eCommerce'
					),
				array(
					'icon'=>'/modules/shop/resources/images/reviews.png', 
					'title'=>'Ratings & Reviews Settings', 
					'url'=>'/shop/reviews_config',
					'description'=>'Disallow duplicate reviews from a single visitor and configure other parameters.',
					'sort_id'=>140,
					'section'=>'eCommerce'
					)
			);
			
			if (Shop_Order::automated_billing_supported())
			{
				$result[] = array(
					'icon'=>'/modules/shop/resources/images/calendar.png', 
					'title'=>'Automated Billing Settings', 
					'url'=>'/shop/autobilling_settings',
					'description'=>'Enable and configure the automatic invoice billing feature.',
					'sort_id'=>140,
					'section'=>'eCommerce'
				);
			}
			
			return $result;
		}

		/**
		 * Builds user permissions interface
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have "Access Level" drop-down:
		 * public function get_access_level_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function buildPermissionsUi($host_obj)
		{
			$host_obj->add_field($this, 'manage_categories', 'Manage categories', 'left')->renderAs(frm_checkbox)->comment('Create, modify or delete categories and manage categories content.', 'above');
			$host_obj->add_field($this, 'manage_products', 'Manage products and groups', 'right')->renderAs(frm_checkbox)->comment('Create, modify or delete products and product groups.', 'above');
			$host_obj->add_field($this, 'manage_shop_settings', 'Manage shop configuration', 'left')->renderAs(frm_checkbox)->comment('Manage tax classes, shipping options and payment methods.', 'above');
			$host_obj->add_field($this, 'access_reports', 'Access reports', 'right')->renderAs(frm_checkbox);
			$host_obj->add_field($this, 'manage_orders_and_customers', 'Manage orders and customers', 'left')->renderAs(frm_checkbox)->comment('Access order list, create and edit orders and customers.', 'above');
			$host_obj->add_field($this, 'customers_export_import', 'Export or import customers', 'right')->renderAs(frm_checkbox)->comment('Export or import the customer list from CSV files.', 'above');
			$host_obj->add_field($this, 'manage_discounts', 'Manage discounts')->renderAs(frm_checkbox)->comment('Manage catalog-level and cart-level price rules.', 'above');
		}

		/**
		 * Returns a list of email template variables provided by the module.
		 * The method must return an array of section names, variable names, 
		 * descriptions and demo-values:
		 * array('Shop variables'=>array(
		 * 	'order_total'=>array('Outputs order total value', '$99.99')
		 * ))
		 * @return array
		 */
		public function listEmailVariables()
		{
			$demo_items = file_get_contents(PATH_APP.'/modules/shop/mailviews/_demo_items_value.htm');
			$demo_items = str_replace('%PRICE%', format_currency(99.99), $demo_items);
			
			$pay_page = Cms_Page::create()->find_by_action_reference('shop:pay');
			$pay_page_url = $pay_page ? root_url($pay_page->url, true).'/' : root_url('pay_page_url', true);
			$password_restore_page = Cms_Page::create()->find_by_action_reference('shop:password_restore_request');
			$password_restore_page_url = $password_restore_page ? root_url($password_restore_page->url, true).'/' : root_url('password_restore_page_url', true);
			$password_restore_page_url .= '19ag812nwqg1239123n23';
			
			return array(
				'Customer variables'=>array(
					'customer_name'=>array('Outputs a full customer name', Phpr::$security->getUser()->firstName.' '.Phpr::$security->getUser()->lastName),
					'customer_first_name'=>array('Outputs a first customer name', Phpr::$security->getUser()->firstName),
					'customer_last_name'=>array('Outputs a last customer name', Phpr::$security->getUser()->lastName),
					'customer_email'=>array('Outputs a customer email address', Phpr::$security->getUser()->email),
					'customer_password'=>array('Outputs a customer password. Can be used only in the registration confirmation template.', '1234567'),
					'customer_password_restore_hash' => array('Outputs the password restore hash, which can be used in a custom password restore link.', '19ag812nwqg1239123n23'),
					'password_restore_page_link' => array('Outputs a link to the password restore page (page using the action shop:password_restore_request), link includes the customer\'s password restore hash.', '<a href="'.$password_restore_page_url.'">'.$password_restore_page_url.'</a>')
				),
				'Order variables'=>array(
					'order_total'=>array('Outputs order total amount', format_currency(125.96)),
					'order_subtotal'=>array('Outputs order subtotal amount', format_currency(99.99)),
					'order_shipping_quote'=>array('Outputs order shipping quote', format_currency(15.99)),
					'order_shipping_tax'=>array('Outputs order shipping tax', format_currency(3.99)),
					'order_tax'=>array('Outputs order goods tax', format_currency(5.99)),
					'order_total_tax'=>array('Outputs total order tax (sales tax + shipping tax)', format_currency(9.98)),
					'cart_discount'=>array('Outputs a total discount amount', format_currency(0)),
					'order_content'=>array('Outputs order items table', $demo_items),
					'order_id'=>array('Outputs order number', '100'),
					'order_date'=>array('Outputs order date', Phpr_DateTime::now()->format('%x')),
					'order_coupon'=>array('Outputs a coupon code', 'SALES_2010'),
					'order_status_comment'=>array('Displays the order status comment specified in the Change Order Status form', 'The package has been moved to the delivery department'),
					'order_previous_status'=>array('Displays a previous order status name', 'New'),
					'order_status_name'=>array('Displays a current order status name', 'Paid'),
					'customer_notes'=>array('Outputs notes provided by the customer', 'Please deliver this order by this Friday!'),
					'payment_page_link'=>array('Outputs a link of the Pay page', '<a href="'.$pay_page_url.'">'.$pay_page_url.'</a>'),
					'tax_incl_label'=>array('Outputs the "tax included" label, in accordance with the label configuration.', '(inlc. GST))'),
					'net_amount'=>array('Outputs order net amount (total - tax).', format_currency(115.97)),
					'billing_customer_name'=>array('Outputs a customer billing name.', 'John Smith'),
					'billing_country'=>array('Outputs a customer billing country name.', 'Canada'),
					'billing_state'=>array('Outputs a customer billing state name.', 'British Columbia'),
					'billing_street_addr'=>array('Outputs a customer billing street address.', '8260 Wharton Pl.'),
					'billing_city'=>array('Outputs a customer billing city.', 'Mission'),
					'billing_zip'=>array('Outputs a customer billing ZIP/Postal code.', 'V2V 7A4'),
					'shipping_customer_name'=>array('Outputs a customer shipping name.', 'John Smith'),
					'shipping_country'=>array('Outputs a customer shipping country name.', 'Canada'),
					'shipping_state'=>array('Outputs a customer shipping state name.', 'British Columbia'),
					'shipping_street_addr'=>array('Outputs a customer shipping street address.', '8260 Wharton Pl.'),
					'shipping_city'=>array('Outputs a customer shipping city.', 'Mission'),
					'shipping_zip'=>array('Outputs a customer shipping ZIP/Postal code.', 'V2V 7A4'),
					'shipping_codes'=>array('Outputs a list of order shipping tracking codes.', '<ul><li>USPS: CJ1111111111US</li></ul>')
				),
				'Product review'=>array(
					'review_author_name'=>array('Outputs a name of the product review author', 'John Smith'),
					'review_author_email'=>array('Outputs an email address of the product review author', 'john@examile.com'),
					'review_product_name'=>array('Outputs a name of the product the review is written for', 'LemonStand'),
					'review_text'=>array('Outputs a text of the review', 'Some text'),
					'review_title'=>array('Outputs the review title text', 'Some title'),
					'review_rating'=>array('Outputs a review rating', '5'),
					'review_edit_url'=>array('Outputs a URL of the Edit Review page in the Aministration Area', 'http://example.com/backend')
				),
				'Order note'=>array(
					'order_note_author'=>array('Outputs the order note author name', 'John Smith'),
					'order_note_id'=>array('Outputs the order number', 100),
					'order_note_text'=>array('Outputs the order note text', 'Please send this order to the Pending status!'),
					'order_note_preview_url'=>array('Outputs a URL of the Order Preview page in the Administration Area', 'http://example.com/backend')
				),
				'Out of stock notification'=>array(
					'out_of_stock_product'=>array('Outputs the out of stock product name', 'Laptop case'),
					'out_of_stock_sku'=>array('Outputs the out of stock product SKU', '1231'),
					'out_of_stock_count'=>array('Outputs the number of units in stock', '100'),
					'out_of_stock_url'=>array('Outputs a URL of the Edit Product page in the Administration Area', 'http://example.com/backend')
				),
				'Low stock notification'=>array(
					'low_stock_product'=>array('Outputs the low stock product name', 'Laptop case'),
					'low_stock_sku'=>array('Outputs the low stock product SKU', '1231'),
					'low_stock_count'=>array('Outputs the number of units still in stock', '100'),
					'low_stock_url'=>array('Outputs a URL of the Edit Product page in the Administration Area', 'http://example.com/backend')
				),
				'Automated billing'=>array(
					'autobilling_report'=>array('Outputs the automated billing report details', 'Invoices processed: 0')
				)
			);
		}

		/**
		 * Returns a list of HTML Editor configurations used by the module
		 * The method must return an array of configuration codes and descriptions:
		 * array('blog_post_content'=>'Blog post')
		 * @return array
		 */
		public function listHtmlEditorConfigs()
		{
			return array(
				'shop_products_categories'=>'Shop product and category descriptions',
				'shop_manufacturers'=>'Product manufacturer descriptions',
				'shop_printable'=>'Invoices, packing slips and other printable documents'
			);
		}

		/**
		 * Returns a list of dashboard indicators in format
		 * array('indicator_code'=>array('partial'=>'partial_name.htm', 'name'=>'Indicator Name')).
		 * Partials must be placed to the module dashboard directory:
		 * /modules/cms/dashboard
		 */
		public function listDashboardIndicators()
		{
			return array(
				'ordertotals'=>array('partial'=>'ordertotals_indicator.htm', 'name'=>'Order Totals'),
				'paidordertotals'=>array('partial'=>'paidordertotals_indicator.htm', 'name'=>'Paid Order Totals'),
			);
		}
		
		/**
		 * Returns a list of dashboard reports in format
		 * array('report_code'=>array('partial'=>'partial_name.htm', 'name'=>'Report Name')).
		 * Partials must be placed to the module dashboard directory:
		 * /modules/cms/dashboard
		 */
		public function listDashboardReports()
		{
			return array(
				'recent_orders'=>array('partial'=>'recentorders_report.htm', 'name'=>'Recent Orders')
			);
		}

		/*
		 * Returns a list of module reports in format
		 * array('report_id'=>'report_name')
		 */
		public function listReports()
		{
			$user = Phpr::$security->getUser();
 			if (!$user->get_permission('shop', 'access_reports'))
				return array();
			
			return array(
				'orders'=>'Orders',
				'products'=>'Products',
				'stock'=>'Stock',
				'categories'=>'Categories',
				'custom_groups'=>'Groups',
				'product_types'=>'Product Types',
				'manufacturers'=>'Manufacturers',
				'coupons'=>'Coupon Usage',
				'taxes'=>'Taxes'
			);
		}
		
		/**
		 * Returns a list of module email variable scopes
		 * array('order'=>'Order')
		 */
		public function listEmailScopes()
		{
			return array('order'=>'Order variables', 'customer'=>'Customer variables');
		}
		
		/**
		 * Generates report dates from the last existing report date
		 * for 10 years in the future.
		 */
		public static function generate_report_dates()
		{
			$last_date = Db_DbHelper::scalar('select report_date from report_dates order by report_date desc limit 0, 1');
			
			$date = Phpr_DateTime::parse($last_date, Phpr_DateTime::universalDateFormat)->addDays(1);

			$interval = new Phpr_DateTimeInterval(1);
			$prevMonthCode = -1;
			$prevYear = $date->getYear();
			$prevYearCode = -1;

			for ($i = 1; $i <= 3650; $i++)
			{
				$year = $date->getYear();
				$month = $date->getMonth();

				if ($prevYear != $year)
					$prevYear = $year;

				if ($prevYearCode != $year)
				{
					$prevYearCode = $year;
					$yDate = new Phpr_DateTime();
					$yDate->setDate( $year, 1, 1 );
					$yearStart = $yDate->toSqlDate();

					$yDate->setDate( $year, 12, 31 );
					$yearEnd = $yDate->toSqlDate();
				}

				/*
				 * Months
				 */

				$monthCode = $year.'.'.$month;
				if ($prevMonthCode != $monthCode)
				{
					$monthStart = $date->toSqlDate();
					$monthFormatted = $date->format('%m.%Y');
					$prevMonthCode = $monthCode;
					$monthEnd = Phpr_Date::lastMonthDate($date)->toSqlDate();
				}

				Db_DbHelper::query(
					"insert into report_dates(report_date, year, month, day, 
						month_start, month_code, month_end, year_start, year_end) 
						values (:report_date, :year, :month, :day, 
						:month_start, :month_code, :month_end,
						:year_start, :year_end)", 
					array(
						'report_date'=>$date->toSqlDate(),
						'year'=>$year, 
						'month'=>$date->getMonth(), 
						'day'=>$date->getDay(), 
						'month_start'=>$monthStart, 
						'month_code'=>$monthCode, 
						'month_end'=>$monthEnd,
						'year_start'=>$yearStart, 
						'year_end'=>$yearEnd 
					));
				$date = $date->addInterval($interval);
			}
		}
		
		/**
		 * Catalog cache version management
		 */
		
		public static function get_catalog_version()
		{
			return Db_ModuleParameters::get( 'shop', 'catalog_version', 0 );
		}
		
		public static function update_catalog_version()
		{
			if (self::$catalog_version_update)
				return;
			
			Db_ModuleParameters::set( 'shop', 'catalog_version', time() );
		}
		
		public static function begin_catalog_version_update()
		{
			self::$catalog_version_update = true;
		}

		public static function end_catalog_version_update()
		{
			self::$catalog_version_update = false;
			self::update_catalog_version();
		}
	}
?>
