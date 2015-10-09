function country_enabled_click(enabled_cb)
{
	var enabled_aa_cb = $('Shop_Country_enabled_in_backend');
	if (enabled_cb.checked)
	{
		enabled_aa_cb.cb_check();
		enabled_aa_cb.cb_disable();
	} else
	{
		enabled_aa_cb.cb_enable();
	}
}

function set_hanlders()
{
	var enabled_cb = $('Shop_Country_enabled');
	if (enabled_cb)
	{
		enabled_cb.addEvent('click', country_enabled_click.pass(enabled_cb));
	}
}

window.addEvent('domready', set_hanlders);