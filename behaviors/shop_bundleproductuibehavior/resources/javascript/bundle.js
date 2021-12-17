var bundle_item_changed = false;

function add_bundle_item()
{
	new PopupForm('preview_on_load_bundle_offer_form', {
		ajaxFields: $('bundle_item_form')
	});
	
	return false;
}

function edit_bundle_item()
{
	new PopupForm('preview_on_load_bundle_offer_form', {
		ajaxFields: {
			'item_id': $('bundle_current_item_id').value,
			'edit_session_key': $('bundle_session_key').value
		}
	});
	
	return false;
}

function delete_bundle_item()
{
	$('bundle_item_form').getForm().sendPhpr(
		'preview_on_delete_bundle_offer',
		{
			confirm: 'Do you really want to delete this bundle item?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'bundle_item_form',
			onFailure: popupAjaxError,
			prepareFunction: register_bundle_offer_change
		}
	);
	
	return false;
}

function refresh_bundle_ui(options)
{
	if (options.cancel_popup !== undefined)
		cancelPopup();
		
	if (options.config_updated !== undefined && options.config_updated == 1)
		register_bundle_offer_change();
		
	var after_update_func = null;
	if (options.afterUpdate !== undefined)
	{
		after_update_func = options.afterUpdate;
		delete options.afterUpdate;
	}

	$('bundle_item_form').getForm().sendPhpr(
		'preview_on_refresh_bundle_ui',
		{
			extraFields: options,
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'bundle_item_form',
			onAfterUpdate: function(){
				make_bundle_offers_sortable();
				make_bundle_offer_items_sortable();
				if (after_update_func)
					after_update_func.call();
			}
		}
	);
	return false;
}

function delete_bundle_products()
{
	var products_selected = $('bundle-item-products').getElements('tr td.checkbox input.list_cb').some(function(element){return element.checked});
	if (!products_selected)
	{
		alert('Please select product(s) to remove.');
		return false;
	}
	
	$('bundle_item_form').getForm().sendPhpr(
		'preview_on_remove_bundle_offer_items',
		{
			confirm: 'Do you really want to remove selected products from the bundle item?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'bundle_item_form',
			onAfterUpdate: function(){
				make_bundle_offers_sortable();
				make_bundle_offer_items_sortable();
			},
			onSuccess: register_bundle_offer_change
		}
	);
	
	return false;
}

function show_add_products_to_bundle_form()
{
	new PopupForm('preview_on_load_add_products_form', {
		ajaxFields: $('bundle_item_form')
	});

 	return false;
}

function update_bundle_item_default_product(checkbox)
{
	register_bundle_offer_change();
	
	var row = $(checkbox).findParent('tr');
	if (checkbox.checked)
		row.addClass('important');
	else
		row.removeClass('important');

	if (!checkbox.checked)
		return;

	if (!$('bundle_current_control_type') || $('bundle_current_control_type').value == 'checkbox')
		return;
	
	$('bundle-item-products').getElements('input.default-bundle-item-product').each(function(cb){
		if (cb != checkbox)
		{
			cb.cb_uncheck();
			var row = $(cb).findParent('tr');
			row.removeClass('important');
		}
	});
}

function update_bundle_offer_item_status(checkbox)
{
	register_bundle_offer_change();

	var row = $(checkbox).findParent('tr');
	if (checkbox.checked)
		row.removeClass('disabled');
	else
		row.addClass('disabled');
}

function make_bundle_offers_sortable()
{
	var list = $('bundle-item-list');
	
	if (list)
		list.makeListSortable('preview_on_set_bundle_offer_order', 'bundle-item-order', 'bundle-item-id', 'bundle-item-handle');
}

function make_bundle_offer_items_sortable()
{
	var list = $('bundle-item-products');
	
	if (list)
		list.makeListSortable('preview_on_set_bundle_offer_item_order', 'bundle-product-order', 'bundle-product-id', 'bundle-product-sort-handle');
}

function update_bundle_price_override(select)
{
	register_bundle_offer_change();

	var $select = $(select);
	
	var option = $select.getSelected();
	if ($type(option) == 'array')
		option = option[0];
	
	$select.findParent('td').getElement('span').set('text', $(option).get('text'));
	
	var price_cell = $select.findParent('tr').getElement('td.price-or-discount');
	var price_input = price_cell.getElement('input');

	if ($select.get('value') != 'default') 
	{
		price_input.show();
		price_input.focus();
		price_cell.removeClass('disabled');
	} else
	{
		price_input.hide();
		price_cell.addClass('disabled');
	}
}

function register_bundle_offer_change()
{
	if ($('save-bundle-item-btn'))
		$('save-bundle-item-btn').removeClass('disabled');

	$('bundle_updated').value = 1;
	
	bundle_item_changed = true;
}


function display_bundle_error(message_data)
{
	var cell = null;
	var row = $('bundle-item-product-' + message_data.product);
	if (row)
	{
		cell = row.getElement('.'+message_data.column);
		if (cell)
			cell.addClass('error');
	}
	
	alert(message_data.message);
	if (cell)
	{
		var input = cell.getElement('input');
		if (input)
			input.focus();
	}
}

function clear_bundle_errors()
{
	if ($('bundle-item-products'))
	{
		$('bundle-item-products').getElements('td.error').each(function(el){
			el.removeClass('error');
		})
	}
}

function save_bundle()
{
	clear_bundle_errors();

	$('bundle_item_form').getForm().sendPhpr(
		'preview_on_save_bundle_offer_changes',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Saving...'), 
			onComplete: LightLoadingIndicator.hide,
			onSuccess: function () {
				$('save-bundle-item-btn').addClass('disabled');
				$('bundle_updated').value = 0;
				bundle_item_changed = false;
			},
			onFailure: function (xhr) {
				var message = xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,"");
				if (message.indexOf('{') !== 0)
					alert(message);
				else
				{
					var message_data = JSON.decode(message);
					
					if ($('bundle_current_item_id').value != message_data.item)
					{
						var opt = {
							afterUpdate: display_bundle_error.pass(message_data),
							bundle_navigate_to_item: message_data.item
						};

						refresh_bundle_ui(opt);
					} else
						display_bundle_error(message_data);
				}
			}
		}
	);
	
	return false;
}

window.addEvent('domready', function(){
	make_bundle_offers_sortable();
	make_bundle_offer_items_sortable();
})

if (window.unload_handlers == undefined)
	window.unload_handlers = [];

window.unload_handlers.push(function(){
	if (bundle_item_changed)
	{
		product_tabs.onTabClick(null, $('bundle_tab'));
		return 'You have unsaved changes on the Bundle tab. The changes will be lost if you leave the page.';
	}
});