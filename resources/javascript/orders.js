function orders_selected()
{
	return $('listShop_Orders_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!orders_selected())
	{
		alert('Please select orders to delete.');
		return false;
	}
	
	new PopupForm('index_onLoadDeleteOrdersForm', {
		ajaxFields: $('listShop_Orders_index_list_body').getForm()
	});

	return false;
}

function restore_selected()
{
	if (!orders_selected())
	{
		alert('Please select orders to restore.');
		return false;
	}
	
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onRestoreSelected',
		{
			confirm: 'Do you really want to restore selected order(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'orders_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function select_order_status(id)
{
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onSelectOrderStatus',
		{
			extraFields: {
				'sidebar_order_status_id': id
			},
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'orders_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function hide_status_selector()
{
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onHideStatusSelector',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'orders_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function show_status_selector()
{
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onShowStatusSelector',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'orders_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function refresh_list()
{
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onRefresh',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'orders_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function change_status()
{
	//this presents an opportunity to block the default behaviour
	try{
		window.fireEvent('onBeforeOrdersChangeStatus');
	}
	catch(err) {
		return false;
	}

	if (!orders_selected())
	{
		alert('Please select orders to change status.');
		return false;
	}



	new PopupForm('index_onLoadChangeStatusForm', {
		ajaxFields: $('listShop_Orders_index_list_body').getForm()
	});

	return false;
}

function print_invoice()
{
	if (!orders_selected())
	{
		alert('Please select orders to print invoice for.');
		return false;
	}

	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onPrintInvoice',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'),
			onComplete: LightLoadingIndicator.hide
		}
	);

	return false;
}

function print_docs()
{
	if (!orders_selected()) {
		alert('Please select orders to print documents for.');
		return false;
	}

	new PopupForm('index_onPrintDocs', {
		closeByEsc: true,
		ajaxFields: $('listShop_Orders_index_list_body').getForm(),
	});

	return false;
}

function print_packing_slip()
{
	if (!orders_selected())
	{
		alert('Please select orders to print packing slip for.');
		return false;
	}
	
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onPrintPackingSlip',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide
		}
	);
	
	return false;
}

function print_shipping_label()
{
	if (!orders_selected())
	{
		alert('Please select orders to print shipping labels for.');
		return false;
	}
	
	$('listShop_Orders_index_list_body').getForm().sendPhpr(
		'index_onPrintShippingLabel',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'),
			onComplete: LightLoadingIndicator.hide
		}
	);
	
	return false;
}

function deselect_orders()
{
	$('listShop_Orders_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_uncheck();})
}

function select_orders(e, select_type)
{
	var e = new Event(e);
	
	switch (select_type)
	{
		case 'all' :
			$('listShop_Orders_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_check();})
		break;
		case 'none' :
			deselect_orders();
		break;
		case 'active' :
			if (!e.shift)
				deselect_orders();
		
			$('listShop_Orders_index_list_body').getElements('tr.order_active td.checkbox input').each(function(element){
				element.cb_check(); 
			})
		break;
		case 'deleted' :
			if (!e.shift)
				deselect_orders();

			$('listShop_Orders_index_list_body').getElements('tr.deleted td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
		default :
			if (!e.shift)
				deselect_orders();

			$('listShop_Orders_index_list_body').getElements('tr.'+select_type+' td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
	}
	
	return false;
}

window.addEvent('domready', function(){
	var el = window.document.html;
	if (el) {
		$(el).bindKeys({'ctrl+alt+s': change_status});
	}
});