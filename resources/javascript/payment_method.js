function payment_method_enabled_click(enabled_cb)
{
	var backend_enabled_cb = $('Shop_PaymentMethod_backend_enabled');
	if (enabled_cb.checked)
	{
		backend_enabled_cb.cb_check();
		backend_enabled_cb.cb_disable();
	} 
	else
	{
		backend_enabled_cb.cb_enable();
	}
}

function set_handlers()
{
	var enabled_cb = $('Shop_PaymentMethod_enabled');
	if (enabled_cb)
	{
		enabled_cb.addEvent('click', payment_method_enabled_click.pass(enabled_cb));
	}
}

window.addEvent('domready', set_handlers);