function products_selected()
{
	return $('listShop_Products_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function duplicate_selected()
{
	if (!products_selected())
	{
		alert('Please select products to duplicate.');
		return false;
	}
	
	$('listShop_Products_index_list_body').getForm().sendPhpr(
		'index_onDuplicateSelected',
		{
//			confirm: 'Do you really want to duplicate selected product(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'products_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function group_selected_products()
{
	var selected = $('listShop_Products_index_list_body').getElements('tr td.checkbox input').filter(function(element){return element.checked});
	
	if (selected.length < 2)
	{
		alert('Please select at least 2 products to group.');
		return false;
	}
	
	new PopupForm('index_onLoadGroupProductsForm', {
		ajaxFields: $('listShop_Products_index_list_body').getForm()
	});

	return false;
}

function enable_disable_selected()
{
	if (!products_selected())
	{
		alert('Please select products to enable or disable.');
		return false;
	}
	
	new PopupForm('index_onLoadEnableDisableProductsForm', {
		ajaxFields: $('listShop_Products_index_list_body').getForm()
	});

	return false;
}

function delete_selected()
{
	if (!products_selected())
	{
		alert('Please select products to delete.');
		return false;
	}
	
	$('listShop_Products_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to DELETE selected product(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'products_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function deselect_products()
{
	$('listShop_Products_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_uncheck();})
}

function select_products(e, select_type)
{
	var e = new Event(e);

	switch (select_type)
	{
		case 'all' :
			$('listShop_Products_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_check();})
		break;
		case 'none' :
			deselect_products();
		break;
		case 'enabled' :
			if (!e.shift)
				deselect_products();

			$('listShop_Products_index_list_body').getElements('tr.product_enabled td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
		case 'disabled' :
			if (!e.shift)
				deselect_products();

			$('listShop_Products_index_list_body').getElements('tr.product_disabled td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
	}
	
	return false;
}

function refresh_ui()
{
	jQuery('#listShop_Products_index_list').tableRowMenu();
	update_scrollable_toolbars();
	update_tooltips();
}

window.addEvent('domready', function(){
	jQuery('#listShop_Products_index_list').tableRowMenu();
	
	$('listShop_Products_index_list').addEvent('listUpdated', function() {
		jQuery('#listShop_Products_index_list').tableRowMenu();
	})
})