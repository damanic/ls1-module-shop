window.addEvent('domready', function(){
	$('Shop_ConfigurationRecord_tax_inclusive_country_id').addEvent('change', function(){
		$('Shop_ConfigurationRecord_tax_inclusive_country_id').getForm().sendPhpr(
			'index_onUpdateTaxInclStates',
			{
				loadIndicator: {show: false},
				onBeforePost: LightLoadingIndicator.show.pass('Loading states...'), 
				onComplete: LightLoadingIndicator.hide
			}
		)
	});
	
	$('Shop_ConfigurationRecord_nested_category_urls').addEvent('click', function(){
		if (this.checked)
			$('form_field_category_urls_prepend_parentShop_ConfigurationRecord').show();
		else
			$('form_field_category_urls_prepend_parentShop_ConfigurationRecord').hide();
	})
});