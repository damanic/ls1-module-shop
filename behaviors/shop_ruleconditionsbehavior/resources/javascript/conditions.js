function conditions_show_settings(condition_id, handler)
{
	$('current_condition_id').value = condition_id; 
	
	new PopupForm(handler, {ajaxFields: $('current_condition_id').getForm()}); 
	return false;
}

function conditions_toggle(element, condition_id, handler)
{
	var new_status_value = $(element).findParent('li').hasClass('collapsed') ? 0 : 1;
	
  	$(element).getForm().sendPhpr(handler, 
	{
		loadIndicator: {show: false},
		extraFields: {'new_status': new_status_value, 'condition_id': condition_id}
	});
	
	if (new_status_value)
		$(element).findParent('li').addClass('collapsed');
	else
		$(element).findParent('li').removeClass('collapsed');
		
	return false;
}

function conditions_add_record(link_element)
{
	link_element = $(link_element);
	var record_id = link_element.getParent().getElement('input.record_id').value;
	var cells = link_element.getParent().getParent().getChildren();
	
	var record_name = [];
	cells.each(function(cell){
		if (!cell.hasClass('iconCell') && !cell.hasClass('expandControl') && cell.innerHTML.trim().length)
			record_name.push(cell.innerHTML.trim());
	})
	
	record_name = record_name.join(', ');

	var table_body = $('added_filter_list');

	/*
	 * Check whether record exists
	 */
	
	var record_exists = table_body.getElements('tr td input.record_id').some(function(field){return field.value == record_id;})
	if (record_exists)
		return false;

	/*
	 * Create row in the added records list
	 */
	
	var icon_cell_content = '<a class="filter_control" href="#" onclick="return conditions_delete_record(this)"><img src="phproad/modules/db/behaviors/db_filterbehavior/resources/images/remove_record.gif" alt="Remove record" title="Remove record" width="16" height="16"/></a>';
	
	var no_data_row = table_body.getElement('tr.noData');
	if (no_data_row)
		no_data_row.destroy();
	
	var row = new Element('tr').inject(table_body);
	var iconCell = new Element('td', {'class': 'iconCell'}).inject(row);
	iconCell.innerHTML = icon_cell_content;
	
	var name_cell = new Element('td', {'class': 'last'}).inject(row);
	name_cell.innerHTML = record_name;
	new Element('input', {'type': 'hidden', 'name': 'condition_reference_ids[]', 'class': 'record_id', 'value': record_id}).inject(name_cell);
	
	if (!(table_body.getChildren().length % 2))
		row.addClass('even');
		
	var current_values = $A($('Shop_PriceRuleCondition_value').value.split(','));
	if (!current_values.some(function(value){value == record_id}))
	{
		if ($('Shop_PriceRuleCondition_value').value.trim().length > 0)
			$('Shop_PriceRuleCondition_value').value += ','+record_id;
		else
			$('Shop_PriceRuleCondition_value').value = record_id;
	}

	return false;
}

function conditions_delete_record(link_element)
{
	link_element = $(link_element);
	var table_body = $('added_filter_list');
	var row = link_element.getParent().getParent();
	var record_id = row.getElement('input.record_id').value;
	row.destroy();
	
	table_body.getChildren().each(function(row, index){
		row.removeClass('even');
		if (index % 2)
			row.addClass('even');
	});
	
	if (!table_body.getChildren().length)
	{
		var row = new Element('tr', {'class': 'noData'}).inject(table_body);
		var el = new Element('td').inject(row);
		el.innerHTML = 'No records added';
	}
	
	var current_values = $A($('Shop_PriceRuleCondition_value').value.split(','));
	var new_values = [];
	current_values.each(function(value){
		if (value != record_id)
			new_values.push(value);
	});
	
	$('Shop_PriceRuleCondition_value').value = new_values.join(',');

	return false;
}

function assign_action_change_event()
{
	var action_element = $(action_menu_id);
	if (action_element)
	{
		action_element.addEvent('change', function(){
			action_element.getForm().sendPhpr('onUpdateAction', {
				loadIndicator: {
					hideOnSuccess: true
				},
				update: 'multi',
				onAfterUpdate: assign_action_change_event
			})
		});
	}
}

function conditions_find_container(element)
{
	return $(element).selectParent('div.main_conditions_container');
}

function conditions_find_new_id(element)
{
	return conditions_find_container(element).getElement('input.new_condition_id');
}

window.addEvent('domready', function(){
	if ((typeof action_menu_id) != 'undefined')
	{
		var action_element = $(action_menu_id);
		assign_action_change_event();
		if (action_element)
			var action_element = $(action_menu_id);
	}
})