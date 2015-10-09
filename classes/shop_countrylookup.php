<?

	class Shop_CountryLookup
	{
		/**
		 * Returns the USPS country name by the country 2-letter ISO code
		 * @param string $iso Specifies the ISO code
		 * @return string Returns the country name or NULL, if the country 
		 * was not found in the database
		 */
		public static function get_usps_name($iso)
		{
			$iso = trim(strtoupper($iso));
			
			if (!strlen($iso))
				return null;

			$name = Db_DbHelper::scalar('select usps_name from shop_country_lookup where iso_code=:code', 
			array(
				'code'=>$iso
			));
			
			if (!strlen($name))
				return null;
				
			return $name;
		}
		
		/**
		* Used for special country cases where USPS considers the country as part of the US.
		* Returns true if USPS would use domestic shipping for this country
		* @param string $iso two letter ISO code of the country
		* @return boolean return true if USPS domestic shipping should be used, false if international shipping
		*/
		public static function get_usps_domestic($iso)
		{
			$iso = trim(strtoupper($iso));
			
			if (!strlen($iso))
				return null;

			$is_domestic = Db_DbHelper::scalar('select usps_domestic from shop_country_lookup where iso_code=:code', 
			array(
				'code'=>$iso
			));
			
			if (!strlen($is_domestic) || !$is_domestic)
				return false;
			
			return true;
		}
	}

?>