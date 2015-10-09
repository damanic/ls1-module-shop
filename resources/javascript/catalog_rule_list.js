function apply_catalog_rules()
{
	if (!confirm('Do you really want to apply catalog price rules?'))
		return false;
		
	$('apply_confirmation').hide();
	$('apply_load_indicator').show();
	$('apply_btn').hide();
	
	$('apply_rules_form').sendPhpr('index_onApplyRules', {
		loadIndicator: {show: false},
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		onSuccess: function(){
			cancelPopup();
		},
		onFailure: popupAjaxError,
		onAfterError: cancelPopup
	})

	return false;
}