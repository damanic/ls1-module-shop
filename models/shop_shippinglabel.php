<?

	class Shop_ShippingLabel
	{
		public $image_data = null;
		public $file_extension = null;
		public $file_size;
		public $link;
		public $title;

		protected static $counter = 0;
		
		public function __construct(&$image_data, $file_extension, $order, $shipping_option, $title = null)
		{
			$this->image_data = $image_data;
			$this->file_extension = $file_extension;
			$this->title = $title;

			if($this->image_data) {
				$this->save_temporary( $order, $shipping_option );
			}
		}
		
		protected function save_temporary($order, $shipping_option)
		{
			self::$counter++;

			$base_path = PATH_APP.'/temp';

			/*
			 * Delete old order label files
			 */

			$pattern = $base_path.'/slb_*.*';
			$files = glob($pattern);

			foreach ($files as $file)
			{
				$time = filectime($file);
				
				$this_label_file = 'slb_'.$order->id.'_'.self::$counter.'_'.$shipping_option->id;
				$file_name = basename($file);
				$force_delete = false;

				if (substr($file_name, 0, strlen($this_label_file)) == $this_label_file)
				{
					$info = pathinfo($file);
					if (isset($info['extension']) && $info['extension'] != $this->file_extension)
						$force_delete = true;
				}
				
				if (!$time || ((time() - $time) > 3600) || $force_delete)
					@unlink($file);
			}

			/*
			 * Save new file to the temporary directory
			 */

			$file_name = 'slb_'.$order->id.'_'.self::$counter.'_'.$shipping_option->id.'.'.$this->file_extension;
			$file_path = PATH_APP.'/temp/'.$file_name;
			if (!@file_put_contents($file_path, $this->image_data))
				throw new Phpr_SystemException('Error saving the label file to '.$file_path);
				
			$this->link = $order->id.'-'.self::$counter.'-'.$shipping_option->id;
			$this->file_size = filesize($file_path);
		}
		
		public static function output_label($link)
		{
			$parts = explode('-', $link);
			if (count($parts) < 2)
				throw new Phpr_ApplicationException('Invalid label link format.');
				
			$order_id = $parts[0];
			$index = $parts[1];
			
			$pattern = PATH_APP.'/temp/slb_'.str_replace('-', '_', $link).'.*';
			$files = glob($pattern);
			if (!$files)
				throw new Phpr_ApplicationException('Label file not found.');

			$file = $files[0];
			
			$pathInfo = pathinfo($file);
			$extension = null;
			if (isset($pathInfo['extension']))
				$extension = strtolower($pathInfo['extension']);
				
			if (!$extension)
				throw new Phpr_ApplicationException('Unknown image label type.');
			
			$mime_types = array(
				'pdf'=>'application/pdf',
				'tif'=>'image/tiff',
				'tiff'=>'image/tiff'
			);
			
			$mime_types = array_merge($mime_types, Phpr::$config->get('auto_mime_types', array()));
			if (array_key_exists($extension, $mime_types))
				$mime_type = $mime_types[$extension];
			else
				$mime_type = 'application/octet-stream';
				
			$file_name = $order_id.'-shipping-label-'.$index.'.'.$extension;
			$size = filesize($file);

			header("Content-type: ".$mime_type);
			header('Content-Disposition: inline'.'; filename="'.$file_name.'"');
			header('Cache-Control: private');
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: pre-check=0, post-check=0, max-age=0');
			header('Accept-Ranges: bytes');
			header('Content-Length: '.$size);
			
			Phpr_Files::readFile( $file );
			die();
		}
	}

?>