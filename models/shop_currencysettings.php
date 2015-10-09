<?php

	class Shop_CurrencySettings extends Backend_SettingsRecord
	{
		public $table_name = 'shop_currency_settings';
		public static $obj = null;

		public $code = 'USD';
		public $dec_point = '.';
		public $thousands_sep = ',';
		public $sign = '$';
		public $iso_4217_code = 840;
		public $sign_before = 1;
		
		public static function get($className = null)
		{
			if (self::$obj !== null)
				return self::$obj;
			
			return self::$obj = parent::get('Shop_CurrencySettings');
		}
		
		public function define_columns($context = null)
		{
			$this->validation->setFormId('settings_form');
			
			$this->define_column('code', 'Currency International Code')->validation()->fn('trim')->required();
			$this->define_column('iso_4217_code', 'ISO 4217 code')->validation()->fn('trim')->required();
			$this->define_column('dec_point', 'Decimal Point')->validation();
			$this->define_column('thousands_sep', 'Thousands Separator')->validation();
			$this->define_column('sign', 'Sign')->validation();
			$this->define_column('sign_before', 'Place sign before number')->validation();
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('code')->comment('Please provide international currency code, e.g. USD. You can find currency codes here: <a href="http://en.wikipedia.org/wiki/ISO_4217" target="_blank">http://en.wikipedia.org/wiki/ISO_4217</a>', 'above', true)->tab('Currency');
			$this->add_form_field('iso_4217_code')->comment('Three-digit currency code, for example 840 for USD.', 'above')->tab('Currency');

			$this->add_form_field('dec_point', 'left')->comment('Character to use as decimal point.', 'above')->tab('Formatting');
			$this->add_form_field('thousands_sep', 'right')->comment('Character to separate thousands.', 'above')->tab('Formatting');
			$this->add_form_field('sign', 'left')->comment('Sign to put beside number, e.g. $.', 'above')->tab('Formatting');
			$this->add_form_field('sign_before', 'right')->tab('Formatting');
		}

		public static function format_currency($num, $decimals = 2)
		{
			if (!strlen($num))
				return null;
			
			$obj = self::get();
			
			$negative = $num < 0;
			$neg_symbol = null;
			if ($negative)
			{
				$num *= -1;
				$neg_symbol = '-';
			}
			
			$num = number_format($num, $decimals, $obj->dec_point, $obj->thousands_sep);
			if ($obj->sign_before)
				return $neg_symbol.$obj->sign.$num;
				
			return $neg_symbol.$num.$obj->sign;
		}
	}

?>