<?

	class Shop_ReviewsConfiguration extends Core_Configuration_Model
	{
		public $record_code = 'reviews_configuration';
		
		public static function create()
		{
			$configObj = new Shop_ReviewsConfiguration();
			return $configObj->load();
		}
		
		protected function build_form()
		{
			$this->add_field('no_duplicate_reviews', 'Do not allow duplicate reviews', 'full', db_number)->renderAs(frm_checkbox)->tab('Reviews')->comment("Use this checkbox to disallow visitors to send multiple reviews for a single product.", "above");
			
			$this->add_field('duplicate_review_message', 'Duplicate review error message', 'full', db_text)->renderAs(frm_text)->tab('Reviews')->comment('A message to display if a visitor tries to post multiple reviews to a single product.', 'above')->cssClassName('checkbox_align')->validation()->fn('trim');

			$this->add_field('email_required', 'Author email address is required', 'full', db_number)->renderAs(frm_checkbox)->tab('Reviews')->comment("Force review authors to specify an email address. Email address is not required for logged in customers.", "above");

			$this->add_field('rating_required', 'Product rating is required', 'full', db_number)->renderAs(frm_checkbox)->tab('Reviews')->comment("Force review authors to specify a product rating.", "above");

			$this->add_field('send_notifications', 'Send notifications about new reviews', 'full', db_number)->renderAs(frm_checkbox)->tab('Reviews')->comment("Notifications will be sent to all LemonStand users who have permissions to manage products.", "above");
		}
		

		protected function init_config_data()
		{
			$this->duplicate_review_message = 'Posting multiple reviews for a single product is not allowed.';
		}
	}

?>