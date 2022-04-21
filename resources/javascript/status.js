function update_template_status()
{
	var $notifyCustomerEl = $('Shop_OrderStatus_notify_customer');
	var $attachDocEl = $('Shop_OrderStatus_notify_attach_document');
	var $customerTemplateEl = $('Shop_OrderStatus_customer_message_template_id');
	$attachDocEl.disabled = !$notifyCustomerEl.checked;
	$attachDocEl.select_update();
	$customerTemplateEl. disabled = !$notifyCustomerEl.checked;
	$customerTemplateEl.select_update();
}



window.addEvent('domready', function(){
	$('Shop_OrderStatus_notify_customer').addEvent('click', update_template_status);
	update_template_status();
})