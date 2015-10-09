<?

	/**
	 * Represents a payment transaction update information.
	 * Objects of this class are returned by some methods of {@link Shop_PaymentMethod} class.
	 * @documentable
	 * @see http://lemonstand.com/docs/managing_payment_transactions Managing payment transactions
	 * @see Shop_PaymentMethod
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_TransactionUpdate
	{
		/**
		 * @var string Specifies the transaction code. 
		 * @documentable
		 */
		public $transaction_status_code;

		/**
		 * @var string Specifies the transaction status name.
		 * Transaction status name are specific for different payment gateways.
		 * @documentable
		 */
		public $transaction_status_name;

		/*
		 * @var string Additional, custom transaction data, can be used differently between payment methods
		 * @documentable
		 */
		public $data_1;
		
		public function __construct($transaction_status_code, $transaction_status_name, $data_1 = null)
		{
			$this->transaction_status_code = $transaction_status_code;
			$this->transaction_status_name = $transaction_status_name;
			$this->data_1 = $data_1;
		}
	}

?>