function categories_selected()
{
	return $('listShop_Categories_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!categories_selected())
	{
		alert('Please select categories to delete.');
		return false;
	}
	
	$('listShop_Categories_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected categories and ALL THEIR SUBCATEGORIES?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: update_scrollable_toolbars,
			update: 'categories_page_content'
		}
	);
	return false;
}

window.addEvent('domready', function(){
	jQuery('#listShop_Categories_index_list').tableRowMenu();
	
	$('listShop_Categories_index_list').addEvent('listUpdated', function() {
		jQuery('#listShop_Categories_index_list').tableRowMenu();
	})
})