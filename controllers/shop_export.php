<?
	class Shop_Export extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';
		protected $required_permissions = array('shop:manage_products');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'export';
			$this->app_module_name = 'Shop';
		}

		public function index()
		{
			$this->app_page_title = 'Export Products';
			$limit = strtoupper(ini_get('memory_limit'));
			if (!strpos($limit, 'G') && (int)$limit < 500) {
				$this->viewData['low_resources'] = true;
			} else {
				$this->viewData['low_resources'] = false;
			}
		}
		
		public function export_csv()
		{
			$iwork_format = post_array_item('export', 'iwork', false);
			$export_format = post_array_item('export', 'format', 'regular');
			$export_images = post_array_item('export', 'images', false);
			
			$this->suppressView();
			try
			{
				if ('regular' == $export_format)
					$output = Shop_ProductExport::export_csv($iwork_format, null, false, $export_images);
				else
					$output = Shop_ProductExportLs2::export_csv($iwork_format, null, false, $export_images);
				
			} catch (Exception $ex)
			{
				$this->app_page_title = 'Export Products';
				$this->_suppressView = false;
				$this->handlePageError($ex);
			}
		}
	}
?>
