function select_countries(select_type)
{
	switch (select_type)
	{
		case 'all' :
			$('listShop_Settings_countries_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_check();})
		break;
		case 'none' :
			$('listShop_Settings_countries_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_uncheck();})
		break;
		case 'enabled' :
			$('listShop_Settings_countries_list_body').getElements('tr.country_enabled td.checkbox input').each(function(element){
				element.cb_check(); 
			})
		break;
		case 'disabled' :
			$('listShop_Settings_countries_list_body').getElements('tr.country_disabled td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
	}
	
	return false;
}

function enable_disable_selected()
{
	var counties_selected = $('listShop_Settings_countries_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
	
	if (!counties_selected)
	{
		alert('Please select countries to enable or disable.');
		return false;
	}
	
	new PopupForm('countries_onLoadEnableDisableCountriesForm', {
		ajaxFields: $('listShop_Settings_countries_list_body').getForm()
	});

	return false;
}