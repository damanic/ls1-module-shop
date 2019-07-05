<?

	class Shop_Customer_Notifications extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';

		public $form_preview_title = 'Notification';
		public $form_create_title = 'Send Customer Message';
		public $form_model_class = 'Shop_CustomerNotification';
		public $form_not_found_message = 'Notification not found';
		public $form_redirect = null;
		public $form_edit_save_redirect = null;

		protected $required_permissions = array('shop:manage_orders_and_customers');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'shop';
			$this->app_page = 'customers';
			$this->app_module_name = 'Shop';

			$this->addCss('/modules/backend/resources/css/html_preview.css?'.module_build('backend'));
		}
		
		public function view($notification_id, $order_id=null)
		{
			$this->viewData['order_id'] = $order_id;
			$this->preview($notification_id);
		}


		
		public function create_formBeforeRender($model)
		{
			$order = null;
			$customer = null;
			$notification = null;
			$template = null;

			$models = $this->get_models();

			if ($models['order']) {
				$order = $models['order'];
				$model->email = $order->billing_email;
				$customer = $order->customer;
			}

			if($models['customer']){
				$customer = $models['customer'];
				$model->email = $customer->email;
			}


			if ($models['notification']) { //copy
				$src_message = $models['notification'];
				$model->email = $src_message->email;
				$model->is_system = $src_message->is_system;
				$model->subject = $src_message->subject;
				$model->message = $src_message->message;

				$model->reply_to_email = $src_message->reply_to_email;
				$model->reply_to_name = $src_message->reply_to_name;

				foreach ($src_message->files as $src_file) {
					$new_file = $src_file->copy();
					$new_file->master_object_class = get_class($model);
					$new_file->field = 'files';
					$new_file->save();
					$model->files->add($new_file, $this->formGetEditSessionKey());
				}

				if(!$customer && is_numeric($src_message->customer_id)){
					$customer = Shop_Customer::create()->find($src_message->customer_id);
				}
				if(!$order && is_numeric($src_message->order_id)){
					$order = $this->get_order_obj($src_message->order_id);
				}
			}


			if ($models['template']) {
				$template  = $models['template'];

				$reply_to = $template->get_reply_address(
					$this->currentUser->email,
					$this->currentUser->name,
					$order ? $order->billing_email : $customer->email,
					$order ? $order->billing_first_name.' '.$order->billing_last_name : $customer->full_name);
				
				if ($reply_to) {
					$keys = array_keys($reply_to);
					$values = array_values($reply_to);
					$model->reply_to_email = $keys[0];
					$model->reply_to_name = $values[0];
				} else {
					$params = System_EmailParams::get();
					$model->reply_to_email = $params->sender_email;
					$model->reply_to_name = $params->sender_name;
				}

				$model->email = $order ? $order->billing_email : $customer->email;
				if($order){
					$model->subject = $this->apply_order_variables($template->subject, $order);
					$model->message = $this->apply_order_variables($template->content, $order);
				} else if($customer){
					$model->subject = $this->apply_customer_variables($template->subject, $order);
					$model->message = $this->apply_customer_variables($template->content, $order);
				}
			}
			
			if (!strlen($model->reply_to_email)) {
				$params = System_EmailParams::get();
				$model->reply_to_email = $params->sender_email;
				$model->reply_to_name = $params->sender_name;
			}

			$this->viewData['customer'] = $customer;
			$this->viewData['order'] = $order;
			$this->viewData['template'] = $template;
			$this->viewData['notification'] = $notification;

			$this->viewData['customer_id'] = $customer ? $customer->id : null;
			$this->viewData['order_id'] = $order ? $order->id : null;
			$this->viewData['template_id'] = $template ? $template->id : null;
			$this->viewData['notification_id'] = $notification ? $notification->id : null;

		}
		
		public function formFindModelObject($recordId)
		{
			if (Phpr::$router->action == 'create')
			{
				$obj = new Shop_CustomerNotification();
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
				$models = $this->get_models();
				$order = $models['order'];
				$customer = $models['customer'];
				if(!$order && !$customer){
					throw new Phpr_ApplicationException('Cannot send notification: Missing Order/Customer association');
				}
				if(!$customer){
					$customer = $order->customer;
				}

				$obj = new Shop_CustomerNotification();
				$data = post($this->form_model_class, array());
				$data['order_id'] = $order ? $order->id : null;
				$data['customer_id'] = $customer ? $customer->id : null;
				$data['created_user_id'] = $this->currentUser->id;

				$obj->save($data, $this->formGetEditSessionKey());

				try {
					$obj->send($order->customer);
				}
				catch (Exception $ex) {
					$obj->delete();
					throw $ex;
				}
				
				Phpr::$session->flash['success'] = 'The message has been successfully sent';
				if($order){
					Phpr::$response->redirect(url('/shop/orders/preview/'.$order->id));
				} else {
					Phpr::$response->redirect(url('/shop/customers/preview/'.$customer->id));
				}

			}
			catch (Exception $ex) {
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function create_onTest()
		{
			try
			{
				$obj = new Shop_CustomerNotification();
				$obj->validate_data(post($this->form_model_class, array()));
				$obj->send_test_message($this->formGetEditSessionKey());
				
				echo Backend_Html::flash_message('The test message has been successfully sent.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function apply_order_variables($message, $order) {
			$message = $order->set_order_and_customer_email_vars(null, $message, null);
			$message = Core_ModuleManager::applyEmailVariables($message, $order, $order->customer);
			return $order->customer->set_customer_email_vars($message);
		}

		protected function apply_customer_variables($message, $customer) {
			return $customer->set_customer_email_vars($message);
		}

		protected function create_onInsertVariable()
		{
			try
			{
				$order_id = post( 'order_id');
				$customer_id = post( 'customer_id');
				$var = '{' . post( 'variable' ) . '}';

				if($order_id) {
					$order = $this->get_order_obj( $order_id );
					echo $this->apply_order_variables( $var, $order );
				} else if(is_numeric($customer_id)){
					$customer = Shop_Customer::create()->find($customer_id);
					if($customer){
						echo $this->apply_customer_variables( $var, $customer );
					}
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function get_models() {

			$models          = array(
				'notification' => null,
				'order'        => null,
				'customer'     => null,
				'template'	=> null
			);

			$notification_id = Phpr::$request->getField( 'notification_id', post('notification_id') );
			if ( is_numeric( $notification_id ) ) {
				$notification = new Shop_CustomerNotification();
				$notification = $notification->find( $notification_id );
				if ( $notification ){
					$models['notification'] = $notification;
				}
			}

			$order_id = Phpr::$request->getField('order_id', post('order_id'));
			if(is_numeric($order_id)){
				$order = $this->get_order_obj($order_id);
				if($order) {
					$models['order'] = $order;
				}
			}

			$customer_id = Phpr::$request->getField('customer_id', post('customer_id'));
			if(is_numeric($customer_id)){
				$customer = Shop_Customer::create()->find($customer_id);
				if($customer){
					$models['customer'] = $customer;
				}
			}

			$template_id = trim(Phpr::$request->getField('template_id', post('template_id')));
			if (is_numeric($template_id)) {
				$template = System_EmailTemplate::create()->find( $template_id );
				if($template){
					$models['template'] = $template;
				}
			}

			return $models;
		}
	}
