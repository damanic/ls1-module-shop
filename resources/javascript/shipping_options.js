function assign_default_shipping_handlers()
{
	if ($('Shop_ShippingParams_default_shipping_country_id'))
	{
		$('Shop_ShippingParams_default_shipping_country_id').addEvent('change', function(){
			$('Shop_ShippingParams_default_shipping_country_id').getForm().sendPhpr(
				'index_onDefaultLocationCountryChange',
				{
					loadIndicator: {show: false},
					onBeforePost: LightLoadingIndicator.show.pass('Loading states...'), 
					onComplete: LightLoadingIndicator.hide
				}
			)
		})
	}
}

window.addEvent('domready', function(){
	if ($('Shop_ShippingParams_country_id'))
	{
		$('Shop_ShippingParams_country_id').addEvent('change', function(){
			$('Shop_ShippingParams_country_id').getForm().sendPhpr(
				'index_onCountryChange',
				{
					loadIndicator: {show: false},
					onBeforePost: LightLoadingIndicator.show.pass('Loading states...'), 
					onComplete: LightLoadingIndicator.hide
				}
			)
		})
	}
	
	assign_default_shipping_handlers();
});

function copy_origin_to_default()
{
	if (!confirm('Are you sure?'))
		return false;
	
	$('Shop_ShippingParams_default_shipping_country_id').getForm().sendPhpr(
		'index_onOriginToDefault',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: assign_default_shipping_handlers
		}
	)

	return false;
}