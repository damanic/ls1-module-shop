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
            $this->define_column('transaction_value_currency_code', 'Value Currency');
			$this->define_column('transaction_complete', 'Settled');
			$this->define_column('transaction_refund', 'Is Refund');
			$this->define_column('transaction_void', 'Is Void');
			$this->define_column('has_disputes', 'Has Disputes');
			$this->define_column('liability_shifted', 'Liability Shifted');
            $this->define_column('settlement_value', 'Settlement Value');
            $this->define_column('settlement_value_currency_code', 'Settlement Value Currency');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('payment_method');
			$this->add_form_field('transaction_status_name', 'left');
			$this->add_form_field('transaction_status_code', 'right');
			$this->add_form_field('transaction_id');
            $this->add_form_field('settlement_value','left');
            $this->add_form_field('settlement_value_currency_code','right');
			$this->add_form_field('actual_user_name');
			$this->add_form_field('user_note')->nl2br(true);
		}

        /**
         * @param Shop_Order $order
         * @param int $payment_method_id
         * @param int $transaction_id
         * @param Shop_TransactionUpdate $transaction_data
         * @param null $user_note
         * @return Shop_PaymentTransaction
         */
        public static function add_transaction(
            $order,
            $payment_method_id,
            $transaction_id,
            $transaction_data,
            $user_note = null
        ) {
            if (!is_a($transaction_data, 'Shop_TransactionUpdate')) {
                throw new Phpr_ApplicationException('Invalid transaction data given, must be instance of Shop_TransactionUpdate');
            }

            $transaction_currency_code = $transaction_data->transaction_value_currency_code ? $transaction_data->transaction_value_currency_code : $order->get_currency_code();
            $settlement_currency_code = $transaction_data->settlement_value_currency_code ? $transaction_data->settlement_value_currency_code : $order->get_currency_code();


            //If the transaction status event has already been logged (with same created_at datetime)
            //we update instead of duplicate.
            $existing_transaction_log = new self();
            $existing_transaction_log->where('order_id = ?', $order->id);
            $existing_transaction_log->where('payment_method_id = ?', $payment_method_id);
            $existing_transaction_log->where('transaction_id = ?', $transaction_id);
            $existing_transaction_log->where('transaction_status_code = ?',  $transaction_data->transaction_status_code);
            $existing_transaction_log->where('created_at = ?',  $transaction_data->created_at);
            $existing_transaction_log = $existing_transaction_log->find();

            $payment_transaction = $existing_transaction_log ? $existing_transaction_log : new self();
            $payment_transaction->order_id = $order->id;
            $payment_transaction->payment_method_id = $payment_method_id;
            $payment_transaction->user_note = $user_note;
            $payment_transaction->transaction_id = $transaction_id;
            $payment_transaction->transaction_status_name = $transaction_data->transaction_status_name;
            $payment_transaction->transaction_status_code = $transaction_data->transaction_status_code;
            $payment_transaction->transaction_value = $transaction_data->transaction_value;
            $payment_transaction->transaction_value_currency_code = $transaction_currency_code;
            $payment_transaction->transaction_complete = $transaction_data->transaction_complete;
            $payment_transaction->transaction_refund = $transaction_data->transaction_refund;
            $payment_transaction->transaction_void = $transaction_data->transaction_void;
            $payment_transaction->has_disputes = $transaction_data->has_disputes;
            $payment_transaction->liability_shifted = $transaction_data->liability_shifted;
            $payment_transaction->settlement_value = $transaction_data->settlement_value;
            $payment_transaction->settlement_value_currency_code = $settlement_currency_code;
            $payment_transaction->data_1 = $transaction_data->data_1;
            if (isset($transaction_data->created_at) && !empty($transaction_data->created_at)) {
                $payment_transaction->auto_create_timestamps = array(); //use gateway timestamp
                $payment_transaction->created_at = $transaction_data->created_at;
            }
            $payment_transaction->save();
            if ($transaction_data->has_disputes) {
                foreach ($transaction_data->get_disputes() as $dispute_update) {
                    $payment_transaction->add_dispute($dispute_update);
                }
            }
            return $payment_transaction;
        }

        /**
         * @deprecated  Use add_transaction()
         * @param $order
         * @param $payment_method_id
         * @param $transaction_id
         * @param $transaction_status_name
         * @param $transaction_status_code
         * @param null $user_note
         * @param null $data
         */
        public static function update_transaction($order, $payment_method_id, $transaction_id, $transaction_status_name, $transaction_status_code, $user_note = null, $data = null)
        {
            $transactionUpdate                          = new Shop_TransactionUpdate();
            $transactionUpdate->transaction_status_code = $transaction_status_code;
            $transactionUpdate->transaction_status_name = $transaction_status_name;
            $transactionUpdate->data_1                  = $data;
            self::add_transaction(  $order,
                $payment_method_id,
                $transaction_id,
                $transactionUpdate,
                $user_note = null
            );
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


        /**
         * Returns the balance of all transaction payments and refunds for the given order
         * @param $order ActiveRecord object Shop_Order
         * @param boolean $include_pending If payments pending should be included (not yet settled)
         * @return float|null Return value if transactions occurred, otherwise NULL
         */
        public static function get_order_balance($order, $include_pending=true){
            $value = null;
            $paid = self::get_order_total_paid($order, $include_pending);
            $refunded = self::get_order_total_refunded($order,$include_pending);
            $total_value = $paid - $refunded;
            if(($paid > 0) || ($refunded > 0)){
                return round($total_value, 2);
            }
            return null; //no transaction activity
        }

        /**
         * Returns total sum of all paid transactions for the given order
         * @param $order ActiveRecord object Shop_Order
         * @param boolean $include_pending If payments pending should be included (not yet settled)
         * @return float Return value if all incoming transactions recorded
         */
        public static function get_order_total_paid($order, $include_pending=true){
                $total_payment = 0;
                $add_where = $include_pending ? null : 'AND transaction_complete = 1';
                $sql_where = "shop_payment_transactions.id in (SELECT MAX(id)
							 	     FROM shop_payment_transactions
							 		 WHERE order_id = :order_id $add_where
							 		 AND transaction_refund IS NULL
							 		 AND transaction_void IS NULL
							 		 GROUP BY transaction_id
							 		 ORDER BY created_at, transaction_complete DESC)";

                $void_transaction_ids = self::get_void_transaction_ids($order);
                if(count($void_transaction_ids)){
                    $sql_where .= ' AND shop_payment_transactions.transaction_id NOT IN (:void_transaction_ids)';
                }
                $bind = array(
                    'order_id' => $order->id,
                    'void_transaction_ids' => $void_transaction_ids
                );
                $transactions = self::create()->where($sql_where, $bind)->find_all();
                if($transactions){
                    foreach($transactions as $transaction){
                        if($transaction->transaction_value ){
                            $total_payment += $transaction->transaction_value;
                        }

                    }
                }
                return round($total_payment, 2);
        }

        /**
         * Returns total sum of all refund transactions for the given order
         * @param $order ActiveRecord object Shop_Order
         * @param boolean $include_pending If refunds pending should be included (not yet settled)
         * @return float Return value if all outgoing transactions recorded
         */
        public static function get_order_total_refunded($order, $include_pending=true){
                $total_refunded = 0;
                $add_where = $include_pending ? null : 'AND transaction_complete = 1';
                $sql_where = "shop_payment_transactions.id in (SELECT MAX(id)
							 	     FROM shop_payment_transactions
							 		 WHERE order_id = :order_id $add_where
							 		 AND transaction_refund = 1
							 		 AND transaction_void IS NULL
							 		 GROUP BY transaction_id
							 		 ORDER BY created_at, transaction_complete DESC)";

                $void_transaction_ids = self::get_void_transaction_ids($order);
                if(count($void_transaction_ids)){
                    $sql_where .= ' AND shop_payment_transactions.transaction_id NOT IN (:void_transaction_ids)';
                }
                $bind = array(
                    'order_id' => $order->id,
                    'void_transaction_ids' => $void_transaction_ids
                );
                $transactions = self::create()->where($sql_where, $bind)->find_all();
                if($transactions){
                    foreach($transactions as $transaction){
                        if($transaction->transaction_value ){
                            $total_refunded += $transaction->transaction_value;
                        }

                    }
                }
                return round($total_refunded, 2);
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
                //The gateway may have created a new transaction ID (eg. refund)
                //Use the updated transaction id when returned.
                $transaction_id = $transaction_update_result->transaction_id ? $transaction_update_result->transaction_id : $this->transaction_id;
                self::add_transaction($order,
                    $this->payment_method_id,
                    $transaction_id,
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
				$transaction_update->fetched_from_gateway = 1;
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