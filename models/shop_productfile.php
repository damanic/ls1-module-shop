<?

	/**
	 * Represents a file in a downloadable product. 
	 * Objects of this class are available through the {@link Shop_Product::$files} property.
	 * See the {@link http://lemonstand.com/docs/order_details_page Creating the Order Details page} article for examples of the class usage.
	 * @property string $size_str Specifies the file size as string.
	 * @documentable
	 * @see http://lemonstand.com/docs/order_details_page Creating the Order Details page
	 * @package shop.models
	 * @author LemonStand eCommerce Inc.
	 */
	class Shop_ProductFile extends Db_File
	{
		public static function create($values = null)
		{
			return new self();
		}
		
		public function __get($name)
		{
			if ($name == 'size_str')
				return Phpr_Files::fileSize($this->size);
				
			return parent::__get($name);
		}

		/**
		 * Returns an URL for downloading the file. 
		 * Use this function to create links to product files. 
		 * Please refer the {@link http://lemonstand.com/docs/order_details_page Creating the Order Details page}
		 * article for details of the method usage. In the default implementation customers can only download files from products which belong
		 * to paid orders. You can develop a custom file download page as it is described in the 
		 * {@link http://lemonstand.com/docs/implementing_downloadable_products/ Integrating downloadable products} article.
		 * 
		 * By default the built-in URL <em>/download_product_file </em> used for product downloadable files.
		 * The <em>$custom_download_url</em> parameter allows to specify an URL of a custom file download page.
		 * A custom download URL should should be specified relative to LemonStand root address, without the 
		 * subdirectory prefix: <em>/custom_download_page</em>
		 * @documentable
		 * @see http://lemonstand.com/docs/implementing_downloadable_products/ Integrating downloadable products
		 * @param Shop_Order $order Specifies the order object.
		 * @param string $mode Specifies the file download mode (disposition). Supported values are: <em>attachment</em>, <em>inline</em>.
		 * @param string $custom_download_url Specifies a custom download URL.
		 * @return string Returns the URL string.
		 */
		public function download_url($order, $mode = null, $custom_download_url = null)
		{
			$download_url = $custom_download_url ? Core_String::normalizeUri($custom_download_url) : 'download_product_file/';
			
			if (!$mode || ($mode != 'inline' && $mode != 'attachment'))
				$mode = 'attachment';
			
			return root_url($download_url.$this->id.'/'.$order->order_hash.'/'.$mode.'/'.$this->name, true);
		}
	}

?>