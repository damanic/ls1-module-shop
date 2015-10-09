<?

	class Shop_PaymentLogRecord extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_payment_log';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_date_format = '%x %H:%M:%S';
		
		public $encrypted_columns = array('request_data', 'response_data', 'raw_response_text');
		
		public $custom_columns = array(
			'ccv_response'=>db_text,
			'avs_response'=>db_text
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('created_at', 'Created At')->dateFormat('%x %H:%M')->validation();
			$this->define_column('message', 'Message');
			$this->define_column('payment_method_name', 'Payment Method');
			$this->define_column('request_data', 'Request Data');
			$this->define_column('response_data', 'Response Data');
			$this->define_column('raw_response_text', 'Response Text');
			
			$this->define_column('ccv_response', 'Card Code Verification Response ');
			$this->define_column('avs_response', 'Address Verification Response ');
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('payment_method_name')->tab('General Information');
			$this->add_form_field('message')->tab('General Information');

			$this->add_form_field('ccv_response')->tab('General Information');
			$this->add_form_field('avs_response')->tab('General Information');

			$this->add_form_field('request_data')->tab('Request Values');
			$this->add_form_field('response_data')->tab('Response Values');
			$this->add_form_field('raw_response_text')->tab('Response Text');
		}

		public function before_save($deferred_session_key = null)
		{
			$this->request_data = serialize($this->request_data);
			$this->response_data = serialize($this->response_data);
		}
		
		protected function after_fetch()
		{
			$this->request_data = strlen($this->request_data) ? unserialize($this->request_data) : array();
			$this->response_data = strlen($this->response_data) ? unserialize($this->response_data) : array();
		}
		
		public function eval_ccv_response()
		{
			if (strlen($this->ccv_response_code))
				return '('.$this->ccv_response_code.') '.$this->ccv_response_text;
		}

		public function eval_avs_response()
		{
			if (strlen($this->avs_response_code))
				return '('.$this->avs_response_code.') '.$this->avs_response_text;
		}
	}

?>