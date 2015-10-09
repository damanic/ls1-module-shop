<?

	/**
	 * Handles the automated billing.
	 */
	class Shop_AutoBilling
	{
		public static function create()
		{
			return new self();
		}
		
		/**
		 * Finds unpaid invoices in accordance with the automated billing settings
		 * and tries to bill customers using their saved payment profiles. Sends
		 * success and file notifications to customers.
		 * @return mixed Returns object representing the operation result details.
		 */
		public function process()
		{
			$this->log('Billing session start ===========');
			
			$result = $this->process_billing();

			$this->log($this->format_result($result));
			
			try
			{
				/*
				 * Notify the administrators
				 */

				try
				{
					$template = System_EmailTemplate::create()->find_by_code('shop:auto_billing_report');
					if ($template)
					{
						$message = str_replace('{autobilling_report}', nl2br(h(self::format_result($result))), $template->content);

						$administrators = Users_User::listAdministrators();
						$template->send_to_team($administrators, $message, null, null, null, null, true);
					}
				} catch (exception $ex)
				{
					throw new Phpr_SystemException('Automated billing report sending failed. Error message: '.$ex->getMessage());
				}
			}
			catch (exception $ex) 
			{
				$this->log($ex->getMessage());
			}
			
			$this->log("Billing session end ===========\n");
			
			return $result;
		}
		
		protected function process_billing()
		{
			@set_time_limit(3600);
			
			$result = array(
				'error_message'=>null,
				'feature_disalbed'=>false,
				'invoice_data'=>array(),
				'total_amount'=>0
			);
			
			$result = (object)$result;

			try
			{
				/*
				 * Load the configuration and exit if the automatic billing is disabled.
				 */

				$params = Shop_AutoBillingParams::get();
				if (!$params)
					throw new Phpr_SystemException('Automated billing configuration record not found in the database.');
				
				if (!$params->enabled)
				{
					$result->feature_disalbed = true;
					return $result;
				}

				/*
				 * Check whether the payment method exists
				 */
			
				$payment_method = $params->payment_method;
			
				if (!$payment_method)
					throw new Phpr_SystemException('Automated payments configuration error - payment method not found.');
				
				$current_time = Phpr_DateTime::now();
				
				$payment_method->define_form_fields();
				$payment_method_obj = $payment_method->get_paymenttype_object();
				
				/*
				 * Find unpaid, not deleted, not failed invoices
				 */

				$orders = new Shop_Order(null);
				$orders->where('automated_billing_fail is null');
				$orders->where('automated_billing_success is null');
				$orders->where('payment_processed is null');
				$orders->where('deleted_at is null');
				$orders->where('parent_order_id is not null');

				/*
				 * Which have been created more than (or exactly on) the number of days specified in 
				 * the automated billing configuration.
				 */

				$interval = new Phpr_DateTimeInterval( $params->billing_period );
				$check_date = Phpr_Date::userDate(Phpr_DateTime::now())->getDate()->substractInterval($interval);

				$orders->where('date(order_datetime) <= ?', $check_date);

				$orders->order('id');
				Backend::$events->fireEvent('shop:onBeforeAutobillingOrderFind', $orders);
				
				$orders = $orders->find_all();
				
				foreach ($orders as $order)
				{
					try
					{
						/*
						 * Check whether the customer has a payment profile for the payment method specified in the 
						 * automated payments configuration.
						 */

						if ($params->payment_method->find_customer_profile($order->customer))
						{
							/*
							 * Payment profile exists - try to pay the invoice with the profile
							 */
							
							$notification_failed = false;
							
							try
							{
								$payment_method_obj->pay_from_profile($payment_method, $order, false, false);
								
								$result->total_amount += $order->total;

								/*
								 * Payment failed - update the order automated billing success flag
								 */

								$this->mark_success($current_time, $order);

								/*
								 * Notify the customer
								 */

								if ($params->success_message_template)
								{
									try
									{
										$order->send_customer_notification($params->success_message_template);
									} catch (exception $message_exception) 
									{
										$notification_failed = $message_exception->getMessage();
									}
								}

								$result->invoice_data[$order->id] = 'PAYMENT PROCESSED.';
								if ($notification_failed)
									$result->invoice_data[$order->id] .= ' Customer notification failed: '.$notification_failed;
							} catch (exception $ex)
							{
								/*
								 * Payment failed - update the order automated billing failed flag
								 */

								$this->mark_fail($current_time, $order);

								/*
								 * Notify the customer
								 */

								if ($params->failed_message_template)
								{
									try
									{
										$order->send_customer_notification($params->failed_message_template);
									} catch (exception $message_exception) 
									{
										$notification_failed = $message_exception->getMessage();
									}
								}

								$result->invoice_data[$order->id] = 'PAYMENT FAILED. '.$ex->getMessage();
								if ($notification_failed)
									$result->invoice_data[$order->id] .= ' Customer notification failed: '.$notification_failed;
							}
						} else
						{
							/*
							 * Payment profile doesn't exist - skip the invoice and mark it as failed
							 */

							$this->mark_fail($current_time, $order);

							$result->invoice_data[$order->id] = 'SKIPPED. Customer payment profile does not exist for '.$params->payment_method->name.' payment method.';
						}
					} catch (exception $ex)
					{
						$result->invoice_data[$order->id] = 'ERROR. '.$ex->getMessage();
					}
				}
			} catch (exception $ex)
			{
				$result->error_message = $ex->getMessage();
				return $result;
			}

			return $result;
		}
		
		/**
		 * Converts object returned by the process() method to plain text string.
		 */
		public static function format_result($result_obj)
		{
			$result = array();
			$result[] = 'Invoices processed: '.count($result_obj->invoice_data);

			if ($result_obj->error_message)
				$result[] = 'Error: '.$result_obj->error_message;

			if ($result_obj->feature_disalbed)
				$result[] = 'The automated billing function is disabled.';
				
			$result[] = 'Total amount processed: '.format_currency($result_obj->total_amount);
				
			foreach ($result_obj->invoice_data as $invoice_num=>$message)
				$result[] = 'Invoice #'.$invoice_num.': '.$message;
				
			return implode("\n", $result);
		}
		
		protected function log($str)
		{
			traceLog($str, 'AUTO_BILLING');
		}
		
		protected function mark_success($current_time, $order)
		{
			Db_DbHelper::query('update shop_orders set automated_billing_success=:current_time where id=:id', array(
				'current_time'=>$current_time,
				'id'=>$order->id)
			);
		}
		
		protected function mark_fail($current_time, $order)
		{
			Db_DbHelper::query('update shop_orders set automated_billing_fail=:current_time where id=:id', array(
				'current_time'=>$current_time,
				'id'=>$order->id)
			);
		}
	}

?>