<?php

	/**
	 * This model represents a notification which has been sent to a customer
	 */
	class Shop_CustomerNotification extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_notifications';

		public $implement = 'Db_AutoFootprints';
		protected $auto_footprints = false;
		public $auto_footprints_visible = true;
		public $auto_footprints_user_not_found_name = 'system';
		
		public $auto_footprints_created_at_name = 'Sent At';
		public $auto_footprints_created_user_name = 'Sent By';
		
		public $has_many = array(
			'files'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"(master_object_class='Shop_OrderNotification' or master_object_class='Shop_CustomerNotification') and field='files'", 'order'=>'id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public static function add($customer, $message_text, $subject, $reply_to = array())
		{
			try
			{
				$obj = self::create();
				$obj->customer_id = $customer->id;
				$obj->email = $customer->email;
				$obj->subject = $subject;
				$obj->message = $message_text;

				if ($reply_to) {
					$keys = array_keys($reply_to);
					$values = array_values($reply_to);
					$obj->reply_to_email = $keys[0];
					$obj->reply_to_name = $values[0];
				} else {
					$params = System_EmailParams::get();
					$obj->reply_to_email = $params->sender_email;
					$obj->reply_to_name = $params->sender_name;
				}

				$obj->save();
			} catch (Exception $ex) {}
		}

		public function define_columns($context = null)
		{
			$this->define_column('email', 'Recipient Email(s)')->validation()->fn('trim')->required()->method('validate_email');
			$this->define_column('subject', 'Subject')->validation()->fn('trim')->required('Please specify the message subject.');
			$this->define_multi_relation_column('files', 'files', 'Attachments', '@name')->defaultInvisible();
			$this->define_column('message', 'Message')->validation()->required('Please enter the message text.');
			
			$this->define_column('reply_to_email', 'Reply-To Email')->validation()->required('Please enter the reply-to email address.')->email(false, 'Please specify a valid reply-to address');
			$this->define_column('reply_to_name', 'Reply-To Name')->validation()->required('Please enter the reply-to name.');
		}
		
		public function define_form_fields($context = null)
		{
			if ($context == 'preview')
			{
				$this->add_form_field('created_at', 'left');
				$this->add_form_field('created_user_name', 'right');
			}

			$this->add_form_field('email', 'left')->renderAs(frm_text);
			$this->add_form_field('subject', 'right');

			$this->add_form_field('reply_to_email', 'left');
			$this->add_form_field('reply_to_name', 'right');
			
			$this->add_form_field('files')->renderAs(frm_file_attachments)->fileDownloadBaseUrl(url('ls_backend/files/get/'));

			$field = $this->add_form_field('message')->renderAs(frm_html)->size('huge');

			if ($context == 'preview')
			{
				$editor_config = System_HtmlEditorConfig::get('system', 'system_email_template');
				$editor_config->apply_to_form_field($field);
			}
		}
		
		public function send_test_message($session_key)
		{
			$template = System_EmailLayout::find_by_code('external');
			$message_text = $template->format($this->message);

			$viewData = array('content'=>$message_text);

			$attachments = array();
			$files = $this->list_related_records_deferred('files', $session_key);
			foreach ($files as $file)
				$attachments[PATH_APP.$file->getPath()] = $file->name;

			$user = Phpr::$security->getUser();
			$replyTo = array($this->reply_to_email=>$this->reply_to_name);

			Core_Email::send('system', 'email_message', $viewData, $this->subject, $user->short_name, $user->email, array(), null, $replyTo, $attachments);
		}
		
		public function validate_email($name, $value)
		{
			$values = explode(',', $value);
			$emails_found = 0;
			foreach ($values as $email)
			{
				$email = trim($email);
				if (!strlen($email))
					continue;

				$email = mb_strtolower($email);
				if (!preg_match("/^[_a-z0-9-\.\=\+]+@[_a-z0-9-\.\=\+]+$/", $email))
					$this->validation->setError('Invalid email address: '.$email, $name, true);

				$emails_found++;
			}

			if (!$emails_found)
				$this->validation->setError('Please specify a recipient email address', $name, true);

			return true;
		}

		
		public function send($customer)
		{
			$template = System_EmailLayout::find_by_code('external');
			$message_text = $template->format($this->message);

			$clone = self::create()->find($this->id);
			
			$viewData = array('content'=>$message_text);

			$attachments = array();
			foreach ($clone->files as $file)
				$attachments[PATH_APP.$file->getPath()] = $file->name;

			$replyTo = array($this->reply_to_email=>$this->reply_to_name);
			
			if (!$this->is_system)
			{
				$recipient_name = $customer->name;
				$recipient_email = $this->email;
				$recipients = array();
			} else
			{
				$recipient_name = null;
				$recipient_email = null;
				$recipients = array();
				
				$emails = explode(',', $this->email);
				foreach ($emails as $email)
				{
					$email = trim($email);
					if (!strlen($email))
						continue;

					$recipients[$email] = $email;
				}
			}
			
			Core_Email::send('system', 'email_message', $viewData, $this->subject, $recipient_name, $recipient_email, $recipients, null, $replyTo, $attachments);
		}

		public function before_save($deferred_session_key = null) 
		{
			$emails = array();
			$emails_values = explode(',', $this->email);
			foreach ($emails_values as $email)
			{
				$email = trim($email);
				if (!strlen($email))
					continue;

				$emails[$email] = $email;
			}

			$this->email = implode(', ', $emails);
		}
	}
?>