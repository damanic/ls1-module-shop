<?php

	class Shop_PaymentTransaction extends Db_ActiveRecord
	{
		public $table_name = 'shop_payment_transactions';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_created_user_name = 'Created/Updated By';
		public $auto_footprints_user_not_found_name = 'system';
		public $auto_footprints_visible = true;

		public $has_many = array(
			'disputes'=>array('class_name'=>'Shop_PaymentTransactionDispute', 'foreign_key'=>'shop_payment_transaction_id', 'order'=>'shop_payment_transaction_disputes.created_at desc, shop_payment_transaction_disputes.id desc', 'delete'=>true)
		);

		public $custom_columns = array('actual_user_name'=>db_text);

		public static function create()
		{
			return new self();
		}
		
		public $belongs_to = array(
			'payment_method'=>array('class_name'=>'Shop_PaymentMethod', 'foreign_key'=>'payment_method_id')
		);

		public function define_columns($context = null)
		{
			$this->define_column('created_at', 'Created/Updated At')->dateFormat('%x %H:%M');
			$this->define_column('transaction_id', 'Transaction ID')->order('asc')->validation()->fn('trim')->required("Please specify transaction ID");
			$this->define_column('transaction_status_name', 'Status Name')->validation()->fn('trim');
			$this->define_column('transaction_status_code', 'Status Code')->validation();
			$this->define_column('user_note', 'User Notes')->validation();
			$this->define_relation_column('payment_method', 'payment_method', 'Payment Method ', db_varchar, '@name');
			$this->define_column('actual_user_name', 'Created/Update By');
			$this->define_column('transaction_value', 'Value');
			$this->define_column('transaction_complete', 'Settled');
			$this->define_column('transaction_refund', 'Is Refund');
			$this->define_column('transaction_void', 'Is Void');
			$this->define_column('has_disputes', 'Has Disputes');
			$this->define_column('liability_shifted', 'Liability Shifted');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('payment_method');
			$this->add_form_field('transaction_status_name', 'left');
			$this->add_form_field('transaction_status_code', 'right');
			$this->add_form_field('transaction_id','left');
			$this->add_form_field('actual_user_name', 'right');
			$this->add_form_field('user_note')->nl2br(true);
		}
		
		public static function update_transaction($order, $payment_method_id, $transaction_id, $transaction_status_name, $transaction_status_code, $user_note = null, $data = null)
		{
			$obj = new self();
			$obj->order_id = $order->id;
			$obj->transaction_id = $transaction_id;
			$obj->transaction_status_name = $transaction_status_name;
			$obj->transaction_status_code = $transaction_status_code;
			$obj->payment_method_id = $payment_method_id;
			$obj->user_note = $user_note;
			$obj->data_1 = $data;
			$obj->save();
		}

		public static function add_transaction($order, $payment_method_id, $transaction_id, $transaction_data, $user_note = null){

				if ( !is_a( $transaction_data, 'Shop_TransactionUpdate' ) ) {
					throw new Phpr_ApplicationException( 'Invalid transaction data given, must be instance of Shop_TransactionUpdate' );
				}

				$payment_transaction                          = new self();
				$payment_transaction->order_id                = $order->id;
				$payment_transaction->payment_method_id       = $payment_method_id;
				$payment_transaction->user_note               = $user_note;
				$payment_transaction->transaction_id          = $transaction_id;
				$payment_transaction->transaction_status_name = $transaction_data->transaction_status_name;
				$payment_transaction->transaction_status_code = $transaction_data->transaction_status_code;
				$payment_transaction->transaction_value       = $transaction_data->transaction_value;
				$payment_transaction->transaction_complete    = $transaction_data->transaction_complete;
				$payment_transaction->transaction_refund      = $transaction_data->transaction_refund;
				$payment_transaction->transaction_void        = $transaction_data->transaction_void;
				$payment_transaction->has_disputes            = $transaction_data->has_disputes;
				$payment_transaction->liability_shifted       = $transaction_data->liability_shifted;
				$payment_transaction->data_1                  = $transaction_data->data_1;
				if ( isset( $transaction_data->created_at ) && !empty( $transaction_data->created_at ) ) {
					$payment_transaction->auto_create_timestamps = array(); //use gateway timestamp
					$payment_transaction->created_at             = $transaction_data->created_at;
				}
				$payment_transaction->save();
				if ( $transaction_data->has_disputes ) {
					foreach ( $transaction_data->get_disputes() as $dispute_update ) {
						$payment_transaction->add_dispute( $dispute_update );
					}
				}
			return $payment_transaction;
		}

		public function add_dispute(Shop_TransactionDisputeUpdate $dispute_update){

			$bind = array(
				'tid' => $this->transaction_id,
				'case_id' => $dispute_update->case_id
			);

			$dispute = Shop_PaymentTransactionDispute::create()->where('api_transaction_id = :tid AND case_id = :case_id', $bind)->find();
			if(!$dispute){
				$dispute = Shop_PaymentTransactionDispute::create();
			}

			$dispute->shop_payment_transaction_id = $this->id;
			$dispute->payment_method_id = $this->payment_method_id;
			$dispute->api_transaction_id = $this->transaction_id;
			$dispute->case_id = $dispute_update->case_id ? $dispute_update->case_id : $this->transaction_id;
			$dispute->amount_disputed = $dispute_update->amount_disputed;
			$dispute->amount_lost = $dispute_update->amount_lost;
			$dispute->status_description = $dispute_update->status_description;
			$dispute->reason_desription = $dispute_update->reason_desription;
			$dispute->case_closed = $dispute_update->case_closed;
			$dispute->notes = $dispute_update->notes;
			$dispute->gateway_api_data = $dispute_update->gateway_api_data;
			$dispute->save();
			$this->disputes->add($dispute);
		}

		public static function get_unique_transactions($order){
			$transactions = array();
			$log_transactions = array();
			foreach($order->payment_transactions as $transaction){
				if($transaction->payment_method_id) {
					if(!isset($log_transactions[$transaction->payment_method_id][$transaction->transaction_id] )){
						$log_transactions[$transaction->payment_method_id][$transaction->transaction_id] = $transactions[] = $transaction;
					}
				}
			}
			return array_reverse($transactions);
		}

		public static function transaction_exists($payment_method_id, $transaction_id){
			$bind = array(
				'payment_method_id' => $payment_method_id,
				'transaction_id' => $transaction_id
			);
			$exists = Db_DbHelper::scalar('SELECT id 
										   FROM shop_payment_transactions 
										   WHERE payment_method_id = :payment_method_id 
										   AND transaction_id = :transaction_id',$bind);

			return $exists ? true : false;
		}

		public static function request_transactions_update($order, $unique_transactions = null){
			if(!$unique_transactions){
				$unique_transactions = self::get_unique_transactions($order);
			}
			foreach($unique_transactions as $transaction) {
				if($transaction->payment_method && $transaction->payment_method->supports_transaction_status_query()) {
					$transaction->request_transaction_status( $order );
				}
			}
		}

		public static function get_void_transaction_ids(Shop_Order $order){
			$sql = "SELECT DISTINCT(transaction_id) FROM shop_payment_transactions 
					WHERE order_id = :order_id
					AND transaction_void = 1
					AND transaction_id IS NOT NULL";
			$bind = array(
				'order_id' => $order->id
			);
			return Db_DbHelper::scalarArray($sql, $bind);
		}

		
		public function list_available_transitions()
		{   
			if (!$this->payment_method)
				throw new Phpr_ApplicationException('Payment method not found');
				
			return $this->payment_method->list_available_transaction_transitions($this->transaction_id, $this->transaction_status_code);
		}
		
		public function eval_actual_user_name()
		{
			return $this->fetched_from_gateway ? 'gateway' : $this->displayField('created_user_name');
		}
		
		public function update_transaction_status($order, $new_transaction_status_code, $new_order_status_id, $comment)
		{
			if (!$this->payment_method)
				throw new Phpr_ApplicationException('Payment method not found');

			if (!strlen($new_transaction_status_code))
				throw new Phpr_ApplicationException('Please specify a new transaction status.');

			$this->payment_method->define_form_fields();

			/*
			 * Update transaction status - gateway
			 */
			$transaction_update_result = null;
			
			try
			{
				$transaction_update_result = $this->payment_method->set_transaction_status($order, $this->transaction_id, $this->transaction_status_code, $new_transaction_status_code);
				if (!$transaction_update_result || !is_object($transaction_update_result) || !($transaction_update_result instanceof Shop_TransactionUpdate))
					throw new Phpr_ApplicationException('Transaction status has not been updated.');
			}
			catch (Exception $ex)
			{
				$message = $ex->getMessage();
				if (strlen($new_order_status_id))
					$message = Core_String::finalize($message)." The order status has not been updated.";

				throw new Phpr_ApplicationException($message);
			}
			
			if (!$transaction_update_result)
				throw new Phpr_ApplicationException('Transaction status has not been updated.');

			/*
			 * Update transaction status - LemonStand database
			 */

			try
			{
				self::add_transaction($order,
					$this->payment_method_id, 
					$this->transaction_id, 
					$transaction_update_result,
					$comment);
			} catch (Exception $ex)
			{
				$message = Core_String::finalize($ex->getMessage());
				
				$message = "The transaction has been successfully updated on the payment gateway, but an error occurred during updating the transaction status in the LemonStand database: ".$message;
				
				if (strlen($new_order_status_id))
					$message .= " The order status has not been updated.";
					
				throw new Phpr_ApplicationException($message);
			}
			
			/*
			 * Update order status
			 */

			if (strlen($new_order_status_id))
			{
				try
				{
					Shop_OrderStatusLog::create_record($new_order_status_id, $order, $comment);
				} catch (Exception $ex)
				{
					$message = Core_String::finalize($ex->getMessage());
					$message = "The transaction has been succesfully updated on the payment gateway, but an error occured during updating the order status: ".$message;

					throw new Phpr_ApplicationException($message);
				}
			}
		}
		
		public function request_transaction_status($order)
		{
			if (!$this->payment_method)
				throw new Phpr_ApplicationException('Payment method not found');

			$this->payment_method->define_form_fields();

			$transaction_update_result = $this->payment_method->request_transaction_status($this->transaction_id);
			if (!$transaction_update_result || !is_object($transaction_update_result) || !($transaction_update_result instanceof Shop_TransactionUpdate))
				throw new Phpr_ApplicationException('Transaction status has not been updated.');

			if(!$transaction_update_result->is_same_status($this)){
				$transaction = self::add_transaction($order,$this->payment_method_id,$this->transaction_id,$transaction_update_result);
				$transaction->fetched_from_gateway = 1;
				$transaction->save();
			}
		}

		public function refresh_transaction_history($order){
			if (!$this->payment_method)
				throw new Phpr_ApplicationException('Payment method not found');
			$this->payment_method->define_form_fields();
			$transaction_updates = $this->payment_method->request_transaction_history($this->transaction_id);
			if(!$transaction_updates || !is_array($transaction_updates) || !count($transaction_updates)){
				throw new Phpr_ApplicationException('Transaction status has not been updated.');
			}
			$old_history = $this->get_transaction_history($order);
			foreach($transaction_updates as $transaction_update){
				if (!$transaction_update || !is_object($transaction_update) || !($transaction_update instanceof Shop_TransactionUpdate)){
					throw new Phpr_ApplicationException('Transaction status has not been updated.');
				}
			}
			$history_updated = false;
			foreach($transaction_updates as $transaction_update){
				$transaction_update->fetched_from_gateway=1;
				$transaction = self::add_transaction($order,$this->payment_method_id,$this->transaction_id,$transaction_update);
				$history_updated = true;
			}
			if($old_history && $history_updated){
				foreach($old_history as $transaction){
					$transaction->delete();
				}
			}
		}

		protected function get_transaction_history($order){
			$bind = array(
				'trans_id'=>$this->transaction_id,
				'order_id' =>$order->id
			);
			return self::create()->where('transaction_id = :trans_id AND order_id = :order_id', $bind)->find_all();
		}


	}

?>