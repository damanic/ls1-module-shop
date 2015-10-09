<?

	class Shop_MergeCustomersModel extends Db_ActiveRecord
	{
		public $table_name = 'core_configuration_records';
		protected $_customer_ids = array();
		public $customers;
		
		public $custom_columns = array(
			'destination_customer'=>db_number
		);

		public function define_columns($context = null)
		{
			$this->define_column('destination_customer', 'Destination customer')->validation()->required('Please select the destination customer.');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('destination_customer')->renderAs(frm_dropdown)->comment('Please select the destination customer to merge the others into. Orders from the other selected customers will be moved to the destination customer. After that the other selected customers will be deleted.' , 'above');
		}
		
		public function get_destination_customer_options()
		{
			$result = array();
			
			foreach ($this->_customer_ids as $customer_id)
			{
				$customer = Shop_Customer::create()->find($customer_id);
				if (!$customer)
					throw new Phpr_ApplicationException(sprintf('The customer with the identifier %s is not found', $customer_id));
				
				$order_num = Db_DbHelper::scalar('select count(*) from shop_orders where customer_id=:customer_id', array('customer_id'=>$customer->id));
				
				$result[$customer->id] = $customer->get_display_name().' ('.$customer->email.'). Registered: '.($customer->guest ? 'yes' : 'no').'. Orders: '.$order_num.'. Created on '.$customer->displayField('created_at');
			}
				
			return $result;
		}
		
		public function init($customer_ids)
		{
			$this->_customer_ids = $customer_ids;
			$this->customers = implode(',', $customer_ids);
			
			$this->define_form_fields();
		}
		
		public function apply($data)
		{
			$this->define_form_fields();
			$this->validate_data($data);
			$products = array();

			$destination_customer = Shop_Customer::create()->find($data['destination_customer']);
			if (!$destination_customer)
				throw new Phpr_ApplicationException('The destination customer is not found');
				
			$customer_ids = explode(',', $data['customers']);
			
			foreach ($customer_ids as $customer_id)
			{
				if ($customer_id == $data['destination_customer'])
					continue;
					
				$source_customer = Shop_Customer::create()->find($customer_id);
				$source_customer->merge_into($destination_customer);
			}
		}
	}

?>