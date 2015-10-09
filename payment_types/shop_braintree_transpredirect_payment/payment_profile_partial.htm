<p>Please provide your credit card information.</p>

<form action="<?= $payment_method_obj->get_payment_profile_form_action($payment_method, $this->customer) ?>" method="post">
	<?= flash_message() ?>
	<ul class="form">
		<? $field_prefix = $payment_method->profle_exists($this->customer) ? null : '[customer]' ?>
		<li class="field text left">
			<label for="CNAME">Cardholder Name</label>
			<div><input autocomplete="off" name="<?= $field_prefix ?>[credit_card][cardholder_name]" value="<?= $payment_method_obj->get_field_value($field_prefix.'[credit_card][cardholder_name]') ?>" id="CNAME" type="text" class="text"/></div>
		</li>		
		
		<li class="field text right">
			<label for="ACCT">Credit Card Number</label>
			<div><input autocomplete="off" name="<?= $field_prefix ?>[credit_card][number]" value="<?= $payment_method_obj->get_field_value($field_prefix.'[credit_card][number]') ?>" id="ACCT" type="text" class="text"/></div>
		</li>

		<li class="field text left">
			<label for="EXPDATE">Expiration Date (MM/YY)</label>
			<div><input autocomplete="off" name="<?= $field_prefix ?>[credit_card][expiration_date]" value="<?= $payment_method_obj->get_field_value($field_prefix.'[credit_card][expiration_date]') ?>" id="EXPDATE" type="text" class="text" maxchars=4/></div>
		</li>

		<li class="field text right">
			<label for="CVV">
				Card Verification Value (CVV)
			</label>
			
			<div><input autocomplete="off" name="<?= $field_prefix ?>[credit_card][cvv]" value="<?= $payment_method_obj->get_field_value($field_prefix.'[credit_card][cvv]') ?>" id="CVV" type="text" class="text"/></div>
		</li>
	</ul>
	<div class="clear"></div>
	
	<?
		$hidden_fields = $payment_method_obj->get_payment_profile_form_hidden_fields($payment_method, $this->customer);
		foreach ($hidden_fields as $name=>$value):
	?>
		<input type="hidden" name="<?= $name ?>" value="<?= h($value) ?>"/>
	<? endforeach ?>

	<input type="submit" value="Submit"/>
</form>