function shipping_option_enabled_click(enabled_cb)
{
	var backend_enabled_cb = $('Shop_ShippingOption_backend_enabled');
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
	var enabled_cb = $('Shop_ShippingOption_enabled');
	if (enabled_cb)
	{
		enabled_cb.addEvent('click', shipping_option_enabled_click.pass(enabled_cb));
	}
}

window.addEvent('domready', set_handlers);