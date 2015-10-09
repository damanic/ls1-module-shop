function customers_selected()
{
	return $('listShop_Customers_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!customers_selected())
	{
		alert('Please select customers to delete.');
		return false;
	}
	
	$('listShop_Customers_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected customer(s)? Customers which have orders will be marked as deleted.',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'customers_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function restore_selected()
{
	if (!customers_selected())
	{
		alert('Please select customer(s) to restore.');
		return false;
	}
	
	$('listShop_Customers_index_list_body').getForm().sendPhpr(
		'index_onRestoreSelected',
		{
			confirm: 'Do you really want to restore selected customer(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'customers_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function deselect_customers()
{
	$('listShop_Customers_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_uncheck();})
}

function select_customers(e, select_type)
{
	var e = new Event(e);
	
	switch (select_type)
	{
		case 'all' :
			$('listShop_Customers_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_check();})
		break;
		case 'none' :
			deselect_customers();
		break;
		case 'active' :
			if (!e.shift)
				deselect_customers();
		
			$('listShop_Customers_index_list_body').getElements('tr.customer_active td.checkbox input').each(function(element){
				element.cb_check(); 
			})
		break;
		case 'deleted' :
			if (!e.shift)
				deselect_customers();

			$('listShop_Customers_index_list_body').getElements('tr.deleted td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
	}
	
	return false;
}

function group_selected_customers()
{
	var selected = $('listShop_Customers_index_list_body').getElements('tr td.checkbox input').filter(function(element){return element.checked});
	
	if (selected.length < 2)
	{
		alert('Please select at least 2 customers to merge.');
		return false;
	}
	
	new PopupForm('index_onLoadMergeCustomersForm', {
		ajaxFields: $('listShop_Customers_index_list_body').getForm()
	});

	return false;
}

function refresh_ui()
{
	jQuery('#listShop_Customers_index_list').tableRowMenu();
	update_scrollable_toolbars();
	update_tooltips();
}