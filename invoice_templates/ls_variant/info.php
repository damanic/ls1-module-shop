<?
	$template_info = array(
		'name'=>'LemonStand Standard Docs',
		'description'=>'Standard LemonStand Commercial Document Templates',
		'custom_render' => false, //if true, template files are ignored, use event shop:onRenderCustomOrderDoc
		'css'=>array(
			
			/*
			 * A list of CSS files and media types to link to the invoice page.
			 *
			 * Use only file names if you need to link a CSS file from the 
			 * template resources/css directory:
			 * 'standard.css'=>'all'
			 *
			 * Use absolute paths if you need to link external CSS files:
			 * 'http://www.url.com/resources/css/print.css'=>'print',
			 * 'http://www.url.com/resources/css/screen.css'=>'all'
			 */

			'standard.css'=>'all'
		),

		'variants' => array(

			/*
			 * A list of documents render options.
			 *
			 * Each variant must have a corresponding file in /variants/ folder.
			 * eg. 'proformainvoice' = /variants/proformainvoice.htm
			 *
			 * Each variant can have an optional 'has_status' or 'on_status' condition.
			 *
			 * 'has_status': Specify a string or array of status codes.
			 *  The variant can only be viewed/printed if the order has a matching
			 *  status in its order status history.
			 *
			 * 'on_status': Specify a string or array of status codes.
			 *  The variant can only be viewed/printed if the current order status code
			 *  matches one of the 'on_status' codes.
			 */

			'invoice' => array(
				'title' => 'Invoice',
				'default' => true,
				'has_status' => array('new')

			),
			'receipt' => array(
				'title' => 'Receipt',
				'has_status' => 'paid'
			),
			'refund' => array(
				'title' => 'Credit Note',
				'has_status' => 'refund'
			),
		)
	);
?>