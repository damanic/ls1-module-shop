<?php

	class Shop_AutoBillingParams extends Backend_SettingsRecord
	{
		public $table_name = 'shop_automated_billing_settings';
		public static $obj = null;
		
		public $belongs_to = array(
			'payment_method'=>array('class_name'=>'Shop_PaymentMethod', 'foreign_key'=>'payment_method_id'),
			'success_message_template'=>array('class_name'=>'System_EmailTemplate', 'foreign_key'=>'success_notification_id'),
			'failed_message_template'=>array('class_name'=>'System_EmailTemplate', 'foreign_key'=>'failed_notification_id')
		);

		public static function get($className = null)
		{
			if (self::$obj !== null)
				return self::$obj;
			
			return self::$obj = parent::get('Shop_AutoBillingParams');
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('enabled', 'Automated Billing Enabled');
			$this->define_column('billing_period', 'Days')->validation()->fn('trim')->required();

			$this->define_relation_column('payment_method', 'payment_method', 'Payment Method', db_varchar, '@name')->validation();
			$this->define_relation_column('success_message_template', 'success_message_template', 'Successful Billing Email', db_varchar, '@code')->validation();
			$this->define_relation_column('failed_message_template', 'failed_message_template', 'Failed Billing Email', db_varchar, '@code')->validation();
		}
		
		public function define_form_fields($context = null)
		{
			$extraFieldClass = $this->enabled ? 'separatedField' : null;
			$this->add_form_field('enabled')->comment('Use this control to enable or disable the automated billing system.')->renderAs(frm_onoffswitcher)->cssClassName($extraFieldClass);

			$extraFieldClass = $this->enabled ? null : 'hidden';
			
			$this->add_form_partial('cron_hint');
			
			$this->add_form_field('billing_period')->comment('The number of days after an invoice has been generated the automated billing is triggered.', 'above')->cssClassName($extraFieldClass);
			$this->add_form_field('payment_method')->comment('Select a payment method the automated billing is applicable to. The list displays only payment methods which support customer payment profiles.', 'above')->cssClassName($extraFieldClass)->emptyOption('<please select>');
			
			$this->add_form_field('success_message_template')->comment('Email message template to be sent to customers in case of successful billing.', 'above')->emptyOption('<please select>')->cssClassName($extraFieldClass);
			$this->add_form_field('failed_message_template')->comment('Email message template to be sent to customers in case of failed billing.', 'above')->emptyOption('<please select>')->cssClassName($extraFieldClass);
		}
		
		public function get_payment_method_options($key_index = -1)
		{
			$payment_methods = Shop_PaymentMethod::create()->order('name')->find_all();
			$result = array();
			foreach ($payment_methods as $method)
			{
				if ($method->supports_payment_profiles())
					$result[$method->id] = $method->name;
			}
			
			return $result;
		}
		
		public function after_validation($deferred_session_key = null) 
		{
			if ($this->enabled && !$this->payment_method)
				$this->validation->setError('Please select a payment method', 'payment_method_id', true);
		}
	}

?>