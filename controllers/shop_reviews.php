<?

	class Shop_Reviews extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Shop_ProductReview';
		public $list_record_url = null;
		public $list_top_partial = 'review_selectors';
		public $list_cell_individual_partial = array('prv_rating'=>'rating_cell');

		public $form_preview_title = 'Review';
		public $form_create_title = 'New Review';
		public $form_edit_title = 'Edit Review';
		public $form_model_class = 'Shop_ProductReview';
		public $form_not_found_message = 'Review not found';
		public $form_redirect = null;
		
		public $form_edit_save_flash = 'The product review has been successfully saved';
		public $form_create_save_flash = 'The product review has been successfully added';
		public $form_edit_delete_flash = 'The product review has been successfully deleted';
		
		public $list_search_enabled = true;
		public $list_search_fields = array('product_link_calculated_join.name');
		public $list_search_prompt = 'find by product name';

		protected $required_permissions = array('shop:manage_products');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'products';
			$this->app_module_name = 'Shop';

			$this->list_record_url = url('/shop/reviews/edit/');
			$this->form_redirect = url('/shop/reviews');
			
			$this->form_redirect = post('referer', Phpr::$request->getReferer(url('shop/reviews')));
			if (strpos($this->form_redirect, 'products/preview') !== false)
				$this->form_redirect .= '#reviews';
		}
		
		public function index()
		{
			$this->app_page_title = 'Reviews';
		}
		
		public function listGetRowClass($model)
		{
			if ($model->prv_moderation_status == Shop_ProductReview::status_new)
				return 'new review_new';
			else 
				return 'review_approved';
		}
		
		protected function index_onDeleteSelected()
		{
			$deleted = 0;

			$ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $ids;

			foreach ($ids as $id)
			{
				$review = null;
				try
				{
					$review = Shop_ProductReview::create()->find($id);
					if (!$review)
						throw new Phpr_ApplicationException('Review with identifier '.$id.' not found.');

					$review->delete();
					$deleted++;
				}
				catch (Exception $ex)
				{
					Phpr::$session->flash['error'] = $ex->getMessage();

					break;
				}
			}

			if ($deleted)
				Phpr::$session->flash['success'] = 'Reviews deleted: '.$deleted;

			$this->renderPartial('reviews_page_content');
		}
		
		protected function index_onApproveSelected()
		{
			$approved = 0;

			$ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $ids;

			foreach ($ids as $id)
			{
				$review = null;
				try
				{
					$review = Shop_ProductReview::create()->find($id);
					if (!$review)
						throw new Phpr_ApplicationException('Review with identifier '.$id.' not found.');

					$review->approve();
					$approved++;
				}
				catch (Exception $ex)
				{
					Phpr::$session->flash['error'] = $ex->getMessage();

					break;
				}
			}

			if ($approved)
				Phpr::$session->flash['success'] = 'Reviews approved: '.$approved;

			$this->renderPartial('reviews_page_content');
		}
	}

?>