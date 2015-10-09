<p class="top_offset">
	Please click the button to pay with Google Checkout.
</p>
<form action="<?= $payment_method_obj->get_form_action($payment_method, $order, true) ?>" method="post">
	<?= flash_message() ?>
	
	<?
		$hidden_fields = $payment_method_obj->get_hidden_fields($payment_method, $order, true);
		foreach ($hidden_fields as $name=>$value):
	?>
		<input type="hidden" name="<?= $name ?>" value="<?= h($value) ?>"/>
	<? endforeach ?>
	<?= backend_button('Pay with Google Checkout', array('href'=>"#", 'onclick'=>'$(this).getForm().submit(); return false;')) ?>
</form>