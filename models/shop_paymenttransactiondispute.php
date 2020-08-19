<?php
class Shop_PaymentTransactionDispute extends Db_ActiveRecord {

	public $table_name = 'shop_payment_transaction_disputes';
	public $implement = 'Db_AutoFootprints';
	public $auto_footprints_visible = true;



	public static function create() {
		return new self();
	}

	public function define_columns($context = null) {
		$this->define_column('case_id', 'Case ID')->validation()->required();
		$this->define_column('api_transaction_id', 'API Transaction ID')->validation()->required();
		$this->define_column('shop_payment_transaction_id', 'Payment Transaction ID')->validation()->required();
		$this->define_column('amount_disputed', 'Amount Disputed');
		$this->define_column('amount_lost', 'Amount Lost')->validation()->fn('trim');
		$this->define_column('status_description', 'Status')->validation();
		$this->define_column('reason_description', 'Reason')->validation();
		$this->define_column('case_closed', 'Case Closed');
		$this->define_column('notes', 'Notes');
	}

	public function define_form_fields($context = null) {}

	public function before_save($deferred_session_key = null) {
		$this->gateway_api_data = serialize($this->gateway_api_data);
	}

	protected function after_fetch() {
		$this->gateway_api_data = strlen($this->gateway_api_data) ? unserialize($this->gateway_api_data) : array();
	}

	public function request_status($transaction = null) {
		
		if(!is_a($transaction,'Shop_PaymentTransaction')){
			$transaction = Shop_PaymentTransaction::create()->find_proxy($this->shop_payment_transaction_id);
		}
		
		if (!$transaction)
			throw new Phpr_ApplicationException('Associated payment transaction not found');


		$payment_method = Shop_PaymentMethod::create()->find($transaction->payment_method_id);
		if(!$payment_method || !$payment_method->supports_transaction_disputes()){
			throw new Phpr_ApplicationException('Cannot retrieve status for this dispute');
		}

		$payment_method->define_form_fields();

		$dispute_update = $payment_method->request_dispute_status($this->case_id);
		if (!$dispute_update || !is_object($dispute_update) || !($dispute_update instanceof Shop_TransactionDisputeUpdate))
			throw new Phpr_ApplicationException('Dispute status has not been updated.');

		if(!$dispute_update->is_same_status($this)){
			$this->amount_disputed = $dispute_update->amount_disputed;
			$this->amount_lost = $dispute_update->amount_lost;
			$this->status_description = $dispute_update->status_description;
			$this->reason_desription = $dispute_update->reason_desription;
			$this->case_closed = $dispute_update->case_closed;
			$this->notes = $dispute_update->notes;
			$this->gateway_api_data = $dispute_update->gateway_api_data;
			$this->save();
		}
	}

	public static function get_transaction_disputes($transaction){
		if(!is_a($transaction, 'Shop_PaymentTransaction')){
			throw new Phpr_ApplicationException( 'Invalid payment transaction object given' );
		}
		 return self::create()->where('shop_payment_transaction_id = ?', $transaction->shop_payment_transaction_id)->find_all();
	}

}