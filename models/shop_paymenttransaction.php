<?php

	class Shop_PaymentTransaction extends Db_ActiveRecord
	{
		public $table_name = 'shop_payment_transactions';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_created_user_name = 'Created/Updated By';
		public $auto_footprints_user_not_found_name = 'system';
		public $auto_footprints_visible = true;

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
			$obj = new self();
			$obj->order_id = $order->id;
			$obj->payment_method_id = $payment_method_id;
			$obj->user_note = $user_note;
			$obj->transaction_id = $transaction_id;
			$obj->transaction_status_name = $transaction_data->transaction_status_name;
			$obj->transaction_status_code = $transaction_data->transaction_status_code;
			$obj->transaction_value = isset($transaction_data->transaction_value) ? $transaction_data->transaction_value : null;
			$obj->transaction_complete = isset($transaction_data->transaction_complete) ? $transaction_data->transaction_complete : null;
			$obj->transaction_refund =  isset($transaction_data->transaction_refund) ? $transaction_data->transaction_refund : null;
			$obj->transaction_void =  isset($transaction_data->transaction_void) ? $transaction_data->transaction_void : null;
			$obj->data_1 = isset($transaction_data->data_1) ? $transaction_data->data_1 : null;
			$obj->save();
			return $obj;
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
				
				$message = "The transaction has been succesfully updated on the payment gateway, but an error occured during updating the transaction status in the LemonStand database: ".$message;
				
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

	}

?>