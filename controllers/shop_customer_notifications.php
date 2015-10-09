<?

	class Shop_Customer_Notifications extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';

		public $form_preview_title = 'Notification';
		public $form_create_title = 'Send Customer Message';
		public $form_model_class = 'Shop_OrderNotification';
		public $form_not_found_message = 'Notification not found';
		public $form_redirect = null;
		public $form_edit_save_redirect = null;

		protected $required_permissions = array('shop:manage_orders_and_customers');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'orders';
			$this->app_module_name = 'Shop';

			$this->addCss('/modules/backend/resources/css/html_preview.css?'.module_build('backend'));
		}
		
		public function view($notification_id, $order_id)
		{
			$this->viewData['order_id'] = $order_id;
			$this->preview($notification_id);
		}
		
		public function create_formBeforeRender($model)
		{
			$order_id = Phpr::$router->param('param1');
			$this->viewData['order_id'] = $order_id;
			$order = $this->viewData['order'] = $this->get_order_obj($order_id);
			$model->email = $order->billing_email;

			$template_id = trim(Phpr::$router->param('param2'));
			if (strlen($template_id) && $template_id != 'no')
			{
				$template  = System_EmailTemplate::create()->find($template_id);
				if (!$template)
					throw new Phpr_ApplicationException('Template not found');

				$reply_to = $template->get_reply_address($this->currentUser->email, $this->currentUser->name, $order->billing_email, $order->billing_first_name.' '.$order->billing_last_name);
				
				if ($reply_to)
				{
					$keys = array_keys($reply_to);
					$values = array_values($reply_to);
					$model->reply_to_email = $keys[0];
					$model->reply_to_name = $values[0];
				} else
				{
					$params = System_EmailParams::get();
					$model->reply_to_email = $params->sender_email;
					$model->reply_to_name = $params->sender_name;
				}

				$model->email = $order->billing_email;
				$model->subject = $this->apply_variables($template->subject, $order);
				$model->message = $this->apply_variables($template->content, $order);
			}

			$copy_from_id = Phpr::$router->param('param3');
			if (strlen($copy_from_id))
			{
				$src_message = new Shop_OrderNotification();
				$src_message = $src_message->find($copy_from_id);
				if ($src_message)
				{
					$model->email = $src_message->email;
					$model->is_system = $src_message->is_system;
					$model->subject = $src_message->subject;
					$model->message = $src_message->message;

					$model->reply_to_email = $src_message->reply_to_email;
					$model->reply_to_name = $src_message->reply_to_name;
					
					foreach ($src_message->files as $src_file)
					{
						$new_file = $src_file->copy();
						$new_file->master_object_class = get_class($model);
						$new_file->field = 'files';
						$new_file->save();
						$model->files->add($new_file, $this->formGetEditSessionKey());
					}
				}
			}
			
			if (!strlen($model->reply_to_email))
			{
				$params = System_EmailParams::get();
				$model->reply_to_email = $params->sender_email;
				$model->reply_to_name = $params->sender_name;
			}
		}
		
		public function formFindModelObject($recordId)
		{
			if (Phpr::$router->action == 'create')
			{
				$obj = new Shop_OrderNotification();
				$obj->define_form_fields();
				return $obj;
			} 

			return $this->getExtension('Db_FormBehavior')->formFindModelObject($recordId);
		}
		
		protected function get_order_obj($order_id)
		{
			if (!strlen($order_id))
				throw new Phpr_ApplicationException('Order not found');

			$order = Shop_Order::create()->find($order_id);
			if (!$order)
				throw new Phpr_ApplicationException('Order not found');
				
			if (!$order->customer)
				throw new Phpr_ApplicationException('Customer not found');

			return $order;
		}
		
		protected function create_onSave()
		{
			try
			{
				$order_id = Phpr::$router->param('param1');
				$order = $this->get_order_obj($order_id);

				$obj = new Shop_OrderNotification();
				$data = post($this->form_model_class, array());
				$data['order_id'] = $order->id;
				$data['created_user_id'] = $this->currentUser->id;

				$obj->save($data, $this->formGetEditSessionKey());

				try
				{
					$obj->send($order->customer);
				}
				catch (Exception $ex)
				{
					$obj->delete();
					throw $ex;
				}
				
				Phpr::$session->flash['success'] = 'The message has been successfully sent';
				Phpr::$response->redirect(url('/shop/orders/preview/'.$order_id));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function create_onTest()
		{
			try
			{
				$obj = new Shop_OrderNotification();
				$obj->validate_data(post($this->form_model_class, array()));
				$obj->send_test_message($this->formGetEditSessionKey());
				
				echo Backend_Html::flash_message('The test message has been successfully sent.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function apply_variables($message, $order)
		{
			$message = $order->set_order_and_customer_email_vars(null, $message, null);
			$message = Core_ModuleManager::applyEmailVariables($message, $order, $order->customer);
			return $order->customer->set_customer_email_vars($message);
		}

		protected function create_onInsertVariable()
		{
			try
			{
				$order_id = Phpr::$router->param('param1');
				$order = $this->get_order_obj($order_id);

				$var = '{'.post('variable').'}';
				echo $this->apply_variables($var, $order);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>