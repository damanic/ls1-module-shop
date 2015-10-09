<?

	/**
	 * This class provides tier price calculation methods
	 */
	class Shop_TierPrice
	{
		/**
		 * Loads tier prices from string.
		 * @param string $str Represents compiled tier price information.
		 * @param string $product_name Specifies the product name (for error messages).
		 * @return array Return tier price array
		 */
		public static function list_tier_prices_from_string($str, $product_name)
		{
			$str = trim($str);
			
			if (!strlen($str))
				return array();
				
			try
			{
				$result = unserialize($str);
				return $result;
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading tier prices for the "'.$product_name.'" product');
			}
		}
		
		/**
		 * Loads tier prices for a specific customer group from string.
		 * @param string $str Represents compiled tier price information.
		 * @param integer $group_id Specifies the customer group identifier.
		 * @param string $product_name Specifies the product name (for error messages).
		 * @param string $parent_tiers Compiled tier price of a parent object (required for option matrix price tiers)
		 * @param float $default_price Specifies the default product price.
		 * @return array Return tier price array
		 */
		public static function list_group_price_tiers($str, $group_id, $product_name, $default_price, $parent_tiers = null)
		{
			$product_price_tiers = self::list_tier_prices_from_string($str, $product_name);
			$parent_tiers = $parent_tiers ? self::list_tier_prices_from_string($parent_tiers, $product_name) : null;

			$result = array();
			$general_price_tiers = array();
			foreach ($product_price_tiers as $tier_id=>$tier)
			{
				if (!is_object($tier))
				{
					$parent_tier = self::find_parent_tier($parent_tiers, $tier_id);
					if (!$parent_tier)
						continue; //  Skip to next tier if the tier is not found
					$parent_tier->price = $tier;
					$tier = $parent_tier;
				}
					
				if (!$tier || !strlen(trim($tier->price)))
					continue; //  Skip to next tier if the tier is not found
				
				if ($tier->customer_group_id == $group_id)
					$result[$tier->quantity] = $tier->price;
					
				if ($tier->customer_group_id == null)
					$general_price_tiers[$tier->quantity] = $tier->price;
			}

			if (!count($result))
				$result = $general_price_tiers;
				
			if (!array_key_exists(1, $result))
				$result[1] = $default_price;
				
			ksort($result);
				
			return $result;
		}
		
		/**
		 * Returns price.
		 * @param string $str Represents compiled tier price information.
		 * @param int $group_id Customer group identifier
		 * @param int $quantity Product quantity
		 * @param string $product_name Specifies the product name (for error messages).
		 * @param float $default_price Specifies the default product price.
		 * @param string $parent_tiers Compiled tier price of a parent object (required for option matrix price tiers)
		 * @return float Returns the product price.
		 */
		public static function eval_tier_price($str, $group_id, $quantity, $product_name, $default_price, $parent_tiers = null)
		{
			$price_tiers = self::list_group_price_tiers($str, $group_id, $product_name, $default_price, $parent_tiers);
			$price_tiers = array_reverse($price_tiers, true);

			foreach ($price_tiers as $tier_quantity=>$price)
			{
				if ($tier_quantity <= $quantity)
					return $price;
			}

			return $default_price;
		}
		
		protected static function find_parent_tier(&$tiers, $tier_id)
		{
			foreach ($tiers as $tier)
			{
				if (property_exists($tier, 'tier_id') && $tier->tier_id == $tier_id)
					return $tier;
			}
			
			return Shop_PriceTier::find_by_id($tier_id);
		}
	}

?>