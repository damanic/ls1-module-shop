<?php

	/**
	 * This model represents a notification which has been sent to a customer
	 */
	class Shop_OrderNote extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_notes';

		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;

		public $auto_footprints_created_at_name = 'Created At';
		public $auto_footprints_created_user_name = 'Created By';

		public $custom_columns = array('note_notifications'=>db_text);
		
		public $notification_users = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('note', 'Note text')->validation()->required('Please enter the note text.');
			$this->define_column('note_notifications', 'Notifications');
		}
		
		public function define_form_fields($context = null)
		{
			if ($context == 'preview')
			{
				$this->add_form_field('created_at', 'left');
				$this->add_form_field('created_user_name', 'right');
			}

			$this->add_form_field('note')->nl2br(true);
			if ($context != 'preview')
				$this->add_form_field('note_notifications');
		}
		
		public function after_create() 
		{
			if (!$this->notification_users)
				return;

			$users = Users_User::create()->where('id IN (?)', array($this->notification_users))->find_all_proxy();
			if ($users)
				$this->notify_users($users);

		}

		public function notify_users($users){

			if(!$this->id){
				//Defer for after create
				foreach($users as $user){
					if($user->id){
						$this->notification_users[] = $user->id;
					}
				}
				return;
			}

			$notification_emails = array();
			foreach ($users as $user) {
				if($user->email) {
					$notification_emails[] = $user->email;
				}
			}

			if (!$notification_emails)
				return;

			$template = System_EmailTemplate::create()->find_by_code('shop:order_note_internal');
			if (!$template)
				return;

			$userSending = Phpr::$security->getUser();
			$fromEmail = $userSending ? $userSending->email : null;
			$fromName = $userSending ? $userSending->name : null;

			$message = $this->set_email_variables($template->content);
			$template->subject = $this->set_email_variables($template->subject);
			$template->send_to_team($notification_emails, $message, $fromEmail,$fromName);
		}
		
		protected function set_email_variables($message)
		{
			$userSending = Phpr::$security->getUser();
			$fromName = $userSending ? $userSending->name : 'System';

			$message = str_replace('{order_note_author}', h($fromName), $message);
			$message = str_replace('{order_note_id}', h($this->order_id), $message);
			$message = str_replace('{order_note_text}', nl2br(h($this->note)), $message);

			$preview_url = Phpr::$request->getRootUrl().url('shop/orders/preview/'.$this->order_id).'#note_'.$this->id;
			$message = str_replace('{order_note_preview_url}', $preview_url, $message);
			
			return $message;
		}
	}
?>