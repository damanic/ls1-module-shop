<?

	class Shop_Reports extends Backend_Controller
	{
		public function index()
		{
			Phpr::$response->redirect(url('/shop/orders_report/'));
		}
	}

?>