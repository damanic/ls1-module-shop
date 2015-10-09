window.addEvent('domready', function(){
	if ($('Shop_CurrencyConversionParams_class_name'))
	{
		$('Shop_CurrencyConversionParams_class_name').addEvent('change', function()
		{
			$('Shop_CurrencyConversionParams_class_name').getForm().sendPhpr(
				'index_onUpdateConverterParams',
				{
					loadIndicator: {show: false},
					onBeforePost: LightLoadingIndicator.show.pass('Loading configuration...'), 
					onComplete: LightLoadingIndicator.hide
				}
			)
		});
	}
})