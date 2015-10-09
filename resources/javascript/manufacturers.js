function manufacturers_selected()
{
	return $('listShop_Manufacturers_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!manufacturers_selected())
	{
		alert('Please select manufacturers to delete.');
		return false;
	}
	
	$('listShop_Manufacturers_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected manufacturer(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'manufacturers_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}
