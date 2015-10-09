<?php

	/**
	 * Represents a customer payment profile.
	 * Payment profiles store data about customers' credit cards. Actual data is stored on a payment gateway, 
	 * and objects of this class keep only identifiers of gateway specific payment profiles.
	 * @documentable
	 * @property string $cc_four_digits_num Contains 4 last digits of the credit card.
	 * @property mixed $profile_data Stores profile information.
	 * @property Phpr_DateTime $created_at Specifies the date and time when the profile record was created.
	 * @property Phpr_DateTime $updated_at Specifies the date and time when the profile record was updated last time.
	 * @see http://lemonstand.com/docs/implementing_customer_payment_profiles Implementing customer payment profiles
	 * @see http://lemonstand.com/docs/pay_page Payment page
	 * @see Shop_PaymentMethod
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_CustomerPaymentProfile extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_payment_profiles';

		public $encrypted_columns = array('profile_data', 'cc_four_digits_num');

		public static function create()
		{
			return new self();
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->profile_data = serialize($this->profile_data);
			$this->cc_four_digits_num = substr($this->cc_four_digits_num, -4);
		}
		
		protected function after_fetch()
		{
			if (strlen($this->profile_data))
			{
				try
				{
					$this->profile_data = @unserialize($this->profile_data);
				}
				catch (exception $ex) {
					$this->profile_data = array();
				}
			} else
				$this->profile_data = array();
		}

		/**
		 * Sets the gateway specific profile information and 4 last digits of the credit card number (PAN)
		 * and saves the profile to the database
		 * @param mixed $profile_data Profile data
		 * @param string $cc_four_digits_num Last four digits of the CC number
		 */
		public function set_profile_data($profile_data, $cc_four_digits_num)
		{
			$this->profile_data = $profile_data;
			$this->cc_four_digits_num = $cc_four_digits_num;
			$this->save();
		}
		
		/**
		 * Sets the 4 last digits of the credit card number (PAN)
		 * and saves the profile to the database
		 * @param string $cc_four_digits_num Last four digits of the CC number
		 */
		public function set_cc_num($cc_four_digits_num)
		{
			$this->cc_four_digits_num = $cc_four_digits_num;
			$this->save();
		}
		
		public function after_save()
		{
			Backend::$events->fireEvent('shop:onCustomerProfileSave', $this);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Triggered when a payment profile is saved to the database.
		 * The event is triggered regardless of whether the customer profile is new or existing.
		 * Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onCustomerProfileSave', $this, 'on_profile_save');
		 * }
		 *  
		 * public function on_profile_save($profile)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event shop:onCustomerProfileSave
		 * @package shop.events
		 * @author LemonStand eCommerce Inc.
		 * @param Shop_CustomerPaymentProfile $profile Specifies the profile object.
		 */
		private function event_onCustomerProfileSave($profile) {}
	}
	
?>