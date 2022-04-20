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


		/**
		 * @var float Specifies the transaction value.
		 * The value is the amount paid or refunded by the transaction
		 * @documentable
		 */
		public $transaction_value;

        /**
         * @var string Specifies the currency code for the transaction value.
         * ISO 4217
         * @documentable
         */
        public $transaction_value_currency_code;

        /**
         * @var float Specifies the amount dispersed/settled in vendors account
         * The value is the amount paid or refunded by the transaction
         * @documentable
         */
        public $settlement_value;

        /**
         * @var string Specifies the currency code for the settlement value.
         * ISO 4217
         * @documentable
         */
        public $settlement_value_currency_code;

		/**
		 * @var int Settlement complete flag
		 * indicates if transaction has settled funds into an account
		 * @documentable
		 */
		public $transaction_complete;

		/**
		 * @var int Is refund flag
		 * indicates if transaction is for a refund
		 * @documentable
		 */
		public $transaction_refund;

		/**
		 * @var int Is void flag
		 * indicates if transaction should be ignored
		 * @documentable
		 */
		public $transaction_void;

		/**
		 * @var int Has disputes
		 * indicates if transaction has been disputed or requires investigation
		 * @documentable
		 */
		public $has_disputes;


		/**
		 * @var array of Shop_TransactionDisputeUpdate
		 * A collection of dispute records if available
		 * @documentable
		 */
		protected $disputes;

		/**
		 * @var int Liability Shifted
		 * indicates if transaction has protections from chargebacks
		 * Eg. 3DS liability shift, Payal Seller Protection
		 * @documentable
		 */
		public $liability_shifted;


		public function __construct($transaction_status_code=null, $transaction_status_name=null, $data_1 = null, $value = null, $complete = null, $refund = null, $void=null) {
			$this->transaction_status_code = $transaction_status_code;
			$this->transaction_status_name = $transaction_status_name;
			$this->transaction_value = $value;
			$this->transaction_complete = $complete;
			$this->transaction_refund = $refund;
			$this->transaction_void = $void;
			$this->data_1 = $data_1;
		}

		public function set_has_disputes(){
			$this->has_disputes = 1;
		}

		public function add_dispute(Shop_TransactionDisputeUpdate $dispute){
			$this->disputes[] = $dispute;
			$this->has_disputes = 1;
		}

		public function get_disputes(){
			return $this->disputes ? $this->disputes : array();
		}

		public function set_liability_shifted(){
			$this->liability_shifted = 1;
		}

        public function is_same_status($old_status)
        {
            $relevant_fields = array(
                'transaction_status_code',
                'transaction_value',
                'transaction_complete',
                'transaction_refund',
                'transaction_void',
                'has_disputes',
                'liability_shifted',
                'settlement_value',
            );
            foreach ($relevant_fields as $field) {
                $newValue = is_numeric($this->$field) ? round($this->$field, 8) : trim($this->$field);
                $oldValue = is_numeric($old_status->$field) ? round($old_status->$field, 8) : trim($old_status->$field);
                $newValue = (string)$newValue;
                $oldValue = (string)$oldValue;
                if ($newValue !== $oldValue) {
                    return false;
                }
            }
            return true;
        }
	}