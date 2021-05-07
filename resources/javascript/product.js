var url_modified = false;
var manufacturer_url_modified = false;
var per_product_shipping_cb = null;

function processTypeChange()
{
	var type_id = $('Shop_Product_product_type_id').get('value');
	var type_settings = product_type_settings.get(type_id);
	if (type_settings)
	{
		type_settings.each(function(visible, tab_id){
			if ($(tab_id))
			{
				if (visible)
					$(tab_id).show();
				else
					$(tab_id).hide();
			}
		});
	}
}

function update_manufacturer_url_title(field_element)
{
	if (!manufacturer_url_modified)
		$('Shop_Manufacturer_url_name').value = convert_text_to_url(field_element.value);
}

function update_url_title(field_element)
{
	if (!url_modified)
		$('Shop_Product_url_name').value = convert_text_to_url(field_element.value);
}

function make_grouped_sortable()
{
	if ($('grouped_products_list_body'))
	{
		$('grouped_products_list_body').makeListSortable('onSetGroupedOrders', 'grouped_order', 'grouped_id', 'grouped_sort_handle');
		$('grouped_products_list_body').addEvent('sortableServerResponse', function(order){
			$('grouped_sort_order').value = order;
		});
	}
}

function make_extras_sortable(session_key)
{
	if ($('extra_options_list_body'+session_key))
		$('extra_options_list_body'+session_key).makeListSortable('onSetExtraOrders', 'extra_order', 'extra_id', 'extra_sort_handle');
}

function make_options_sortable(session_key)
{
	if ($('options_list_body'+session_key))
		$('options_list_body'+session_key).makeListSortable('onSetOptionOrders', 'option_order', 'option_id', 'sort_handle');
}

function make_properties_sortable(session_key)
{
	if ($('properties_list_body'+session_key))
		$('properties_list_body'+session_key).makeListSortable('onSetPropertyOrders', 'option_order', 'option_id', 'sort_handle');
}

function copy_grouped_ghost_params()
{
	if ($('grouped_products_list_body'))
	{
		var grouped_ghost_row = $('grouped_products_list_body').getElement('tr.grouped_ghost');
		if (!grouped_ghost_row)
			return;
			
		grouped_ghost_row.getElement('span.grouped_option_name').innerHTML = $('Shop_Product_grouped_option_desc').value.htmlEscape()+' (this product)';
		grouped_ghost_row.getElement('span.grouped_product_name').innerHTML = $('Shop_Product_name').value.htmlEscape();
		grouped_ghost_row.getElement('span.grouped_sku').innerHTML = $('Shop_Product_sku').value.htmlEscape();
		grouped_ghost_row.getElement('span.grouped_sku').innerHTML = $('Shop_Product_sku').value.htmlEscape();
		grouped_ghost_row.getElement('span.grouped_price').innerHTML = $('Shop_Product_price').value.htmlEscape();
		grouped_ghost_row.getElement('span.grouped_enabled').innerHTML = $('Shop_Product_enabled').checked ? 'Yes' : 'No';
		if (!$('Shop_Product_enabled').checked)
			grouped_ghost_row.addClass('disabled');
		else
			grouped_ghost_row.removeClass('disabled');

		grouped_ghost_row.getElement('input.in_stock_value').value = $('Shop_Product_in_stock').value.htmlEscape();
	}
}

function init_grouped_stock_handlers()
{
	if ($('grouped_products_list_body'))
	{
		var grouped_ghost_row = $('grouped_products_list_body').getElement('tr.grouped_ghost');
		if (!grouped_ghost_row)
			return;
		
		var master_in_stock_input = grouped_ghost_row.getElement('input.in_stock_value')
		if (!master_in_stock_input)
			return;
			
		master_in_stock_input.addEvent('change', function(){
			$('Shop_Product_in_stock').value = master_in_stock_input.value.htmlEscape();
		})
	}
}

function updateGroupedGhost()
{
	if ($('grouped_products_list_body'))
	{
		var grouped_ghost_row = $('grouped_products_list_body').getElement('tr.grouped_ghost');
		if (!grouped_ghost_row)
			return;
			
		grouped_ghost_row.getElement('span.grouped_option_name').innerHTML = $('Shop_Product_grouped_option_desc').value.htmlEscape()+' (this product)';
	}
}

function checkGroupedCreation()
{
	if (!$('Shop_Product_grouped_attribute_name').value.trim().length)
	{
		alert('Please specify the grouped attribute name.');
		$('Shop_Product_grouped_attribute_name').focus();
		return false;
	}

	if (!$('Shop_Product_grouped_option_desc').value.trim().length)
	{
		alert('Please specify this product grouped option description.');
		$('Shop_Product_grouped_option_desc').focus();
		return false;
	}
	
	return true;
}

function assign_manufacturer_handler()
{
	var list = $('Shop_Product_manufacturer_id');
	if (list)
	{
		list.addEvent('change', function(){
			if (list.get('value') == -1)
				new PopupForm('onLoadAddManufacturerForm');
		})
	}
}

function update_customer_group_filter_visibility(customer_group_filter_cb)
{
	if (customer_group_filter_cb.checked)
		$('form_field_customer_groupsShop_Product').show();
	else
		$('form_field_customer_groupsShop_Product').hide();
}

function update_perproduct_shipping_visibility(switcher_cb)
{
	if (switcher_cb.checked)
		$('form_field_perproduct_shipping_costShop_Product').show();
	else
		$('form_field_perproduct_shipping_costShop_Product').hide();
}

function enabled_click(ecb, dcb)
{
	if (ecb.checked)
		dcb.cb_uncheck();
}

function disable_compl_click(dcb, ecb)
{
	if (dcb.checked)
		ecb.cb_uncheck();
}

function update_on_sale_controls(cb, li, input)
{
	if (cb.checked) 
	{
		li.show();
		if (input)
			input.focus();
	}
	else
		li.hide();
}

function init_grouped_on_sale_controls()
{
	var on_sale_cb = $('groupedShop_Product_on_sale');
	var sale_price_or_discount_li = $('groupedform_field_sale_price_or_discountShop_Product');
	var input = $('groupedShop_Product_sale_price_or_discount');
	if (on_sale_cb && sale_price_or_discount_li)
	{
		on_sale_cb.addEvent('click', update_on_sale_controls.pass([on_sale_cb, sale_price_or_discount_li, input]));
		update_on_sale_controls(on_sale_cb, sale_price_or_discount_li, null);
	}
}

function update_property_known_values()
{
	var property_name_element = $('Shop_ProductProperty_name') || $('groupedShop_ProductProperty_name');
	var indicator_element = $('form_field_value_pickupShop_ProductProperty') || $('groupedform_field_value_pickupShop_ProductProperty');
	var update_element = $('form_field_container_value_pickupShop_ProductProperty') || $('groupedform_field_container_value_pickupShop_ProductProperty');
	
	property_name_element.getForm().sendPhpr('onUpdatePropertyValues', {
		loadIndicator: {
			injectInElement: true, 
			element: indicator_element,
			src: ls_root_url('/phproad/resources/images/form_load_50x50.gif'),
			hideOnSuccess: true
		}, 
		update: update_element,
		onAfterUpdate: init_property_value_selector
	});
}

function init_property_value_selector()
{

	var pickup_element = $('Shop_ProductProperty_value_pickup') || $('groupedShop_ProductProperty_value_pickup');
	pickup_element.addEvent('change', update_property_value);
}

function update_property_value()
{
	var pickup_element = $('Shop_ProductProperty_value_pickup') || $('groupedShop_ProductProperty_value_pickup');
	var property_name_element = $('Shop_ProductProperty_name') || $('groupedShop_ProductProperty_name');
	var indicator_element = $('form_field_valueShop_ProductProperty') || $('groupedform_field_valueShop_ProductProperty');
	var update_element = $('form_field_container_valueShop_ProductProperty') || $('groupedform_field_container_valueShop_ProductProperty');

	if (!pickup_element || !update_element)
		return;

	property_name_element.getForm().sendPhpr('onUpdatePropertyValue', {
		loadIndicator: {
			injectInElement: true, 
			element: indicator_element,
			src: ls_root_url('/phproad/resources/images/form_load_50x50.gif'),
			hideOnSuccess: true
		}, 
		update: update_element
	});
}

function update_grouped_pps_controls_visibility(cb, enable_cb)
{
	if (!cb.checked)
	{
		$('groupedform_field_enable_perproduct_shipping_costShop_Product').show()
	} else {
		$('groupedform_field_enable_perproduct_shipping_costShop_Product').hide();
	}
	
	update_grouped_pps_grid_visibility(enable_cb, cb);
}

function update_grouped_pps_grid_visibility(cb, parent_cb)
{
	if (parent_cb.checked)
	{
		$('groupedform_field_perproduct_shipping_costShop_Product').hide();
		return;
	}
		
	if (cb.checked)
		$('groupedform_field_perproduct_shipping_costShop_Product').show()
	else
		$('groupedform_field_perproduct_shipping_costShop_Product').hide();
	
	realignPopups();
}

function init_grouped_shipping_controls()
{
	var 
		use_parent_cb = $('groupedShop_Product_perproduct_shipping_cost_use_parent'),
		enable_cb = $('groupedShop_Product_enable_perproduct_shipping_cost');

	if (use_parent_cb && enable_cb)
	{
		use_parent_cb.addEvent('click', function() {
			update_grouped_pps_controls_visibility(use_parent_cb, enable_cb);
		});
		
		enable_cb.addEvent('click', function() {
			update_grouped_pps_grid_visibility(enable_cb, use_parent_cb);
		});

		update_grouped_pps_controls_visibility(use_parent_cb, enable_cb);
	}
}

function init_properties_form()
{
	var property_name_element = $('Shop_ProductProperty_name') || $('groupedShop_ProductProperty_name');
	
	new InputChangeTracker(property_name_element,  {regexp_mask: '^.*$'}).addEvent('change', update_property_known_values);
	init_property_value_selector();
}

window.addEvent('domready', function(){
	if ($('phpr_lock_mode'))
		return;
		
	jQuery('#splitter-table').backendSplitter({
		minWidth: 300,
		saveWidth: true
	});
	jQuery('#content').fullHeightLayout();

	window.addEvent('phpr_editor_resized', backend_trigger_layout_updated);
	window.addEvent('phpr_form_collapsable_updated', backend_trigger_layout_updated);
	window.addEvent('phpreditoradded', function(){
		(function(){backend_trigger_layout_updated();}).delay(600);
	});
	window.addEvent('onAfterAjaxUpdateGlobal', backend_trigger_layout_updated);

	if ($('sidebar_tabs'))
		new TabManager('sidebar_tabs', 'sidebar_pages', {trackTab: false});

	assign_manufacturer_handler();
	
	$('Shop_Product_product_type_id').addEvent('change', processTypeChange);

	var name_field = $('Shop_Product_name');
	if (name_field && $('new_record_flag'))
	{
		name_field.addEvent('keyup', update_url_title.pass(name_field));
		name_field.addEvent('change', update_url_title.pass(name_field));
		name_field.addEvent('paste', update_url_title.pass(name_field));
	}
	
	if ($('new_record_flag'))
	{
		var url_element = $('Shop_Product_url_name');
		url_element.addEvent('change', function(){url_modified=true;});
	}
	
	var enabled_cb = $('Shop_Product_enabled');
	var disable_compl_cb = $('Shop_Product_disable_completely');
	if (enabled_cb && disable_compl_cb)
	{
		enabled_cb.addEvent('click', enabled_click.pass([enabled_cb, disable_compl_cb]));
		disable_compl_cb.addEvent('click', disable_compl_click.pass([disable_compl_cb, enabled_cb]));
	}
	
	var on_sale_cb = $('Shop_Product_on_sale');
	var sale_price_or_discount_li = $('form_field_sale_price_or_discountShop_Product');
	var discount_input = $('Shop_Product_sale_price_or_discount');
	if (on_sale_cb && sale_price_or_discount_li)
	{
		on_sale_cb.addEvent('click', update_on_sale_controls.pass([on_sale_cb, sale_price_or_discount_li, discount_input]));
		update_on_sale_controls(on_sale_cb, sale_price_or_discount_li, null);
	}
	
	if ($('tab_grouped'))
		$('tab_grouped').addEvent('onTabClick', copy_grouped_ghost_params);
	
	make_grouped_sortable();
	
	if ($('form_session_keyShop_Product'))
	{
		make_extras_sortable($('form_session_keyShop_Product').value);
		make_options_sortable($('form_session_keyShop_Product').value);
		make_properties_sortable($('form_session_keyShop_Product').value);
	}
	
	if ($('Shop_Product_grouped_option_desc'))
		new InputChangeTracker($('Shop_Product_grouped_option_desc'), {regexp_mask: '^.*$'}).addEvent('change', updateGroupedGhost);
		
	var customer_group_filter_cb = $('Shop_Product_enable_customer_group_filter');
	if (customer_group_filter_cb)
	{
		if (!customer_group_filter_cb.checked)
			$('form_field_customer_groupsShop_Product').hide();
			
		customer_group_filter_cb.addEvent('click', update_customer_group_filter_visibility.pass(customer_group_filter_cb));
	}
	
	per_product_shipping_cb = $('Shop_Product_enable_perproduct_shipping_cost');
	if (per_product_shipping_cb)
	{
		per_product_shipping_cb.addEvent('click', update_perproduct_shipping_visibility.pass(per_product_shipping_cb));
	}
	
	init_grouped_stock_handlers();
});

window.addEvent('grid_columns_adjusted', function(table_id){
	if (table_id == 'Shop_Product_perproduct_shipping_cost' && per_product_shipping_cb)
		update_perproduct_shipping_visibility(per_product_shipping_cb);
})