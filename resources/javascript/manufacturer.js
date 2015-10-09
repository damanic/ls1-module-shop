var url_modified = false;

function update_url_title(field_element)
{
	if (!url_modified)
		$('Shop_Manufacturer_url_name').value = convert_text_to_url(field_element.value);
}

window.addEvent('domready', function(){
	$('Shop_Manufacturer_country_id').addEvent('change', function(){
		$('Shop_Manufacturer_country_id').getForm().sendPhpr(
			'onUpdateStatesList',
			{
				loadIndicator: {show: false}
			}
		)
	});
	
	var name_field = $('Shop_Manufacturer_name');
	if (name_field && $('new_record_flag'))
	{
		name_field.addEvent('keyup', update_url_title.pass(name_field));
		name_field.addEvent('change', update_url_title.pass(name_field));
		name_field.addEvent('paste', update_url_title.pass(name_field));
	}
	
	if ($('new_record_flag'))
	{
		var url_element = $('Shop_Manufacturer_url_name');
		url_element.addEvent('change', function(){url_modified=true;});
	}
});