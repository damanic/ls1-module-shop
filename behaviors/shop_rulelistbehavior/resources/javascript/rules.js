function rules_after_drag()
{
	var list = $('rule_list');
	var items = list.getChildren();
	var last_index = items.length - 1;
	var prev_is_terminating = false;
	
	items.each(function(item, index){
		item.setStyle('z-index', 1000 + last_index - index);
		
		if (index == 0)
		{
			item.removeClass('last');
			item.addClass('first');
		}

		if (index == last_index)
		{
			item.removeClass('first');
			item.addClass('last');
		}

		if (index != last_index && index != 0)
		{
			item.removeClass('first');
			item.removeClass('last');
		}
		
		if (prev_is_terminating)
			item.addClass('after_terminating');
		else
			item.removeClass('after_terminating');
		
		prev_is_terminating = item.hasClass('terminating');
	})
}

function rules_toggle(element, rule_id, handler)
{
	var new_status_value = $(element).findParent('li').hasClass('collapsed') ? 0 : 1;
	
  	$(element).getForm().sendPhpr(handler, 
	{
		loadIndicator: {show: false},
		extraFields: {'new_status': new_status_value, 'rule_id': rule_id}
	});
	
	if (new_status_value)
		$(element).findParent('li').addClass('collapsed');
	else
		$(element).findParent('li').removeClass('collapsed');
		
	return false;
}

function rules_assign_sortables()
{
	var rule_list = $('rule_list');
	if (rule_list)
	{
		rule_list.makeListSortable('index_onSetRuleOrders', 'rule_order', 'rule_id', 'drag_handle');
		rule_list.addEvent('dragComplete', rules_after_drag);
	}
}

function rules_delete(element, rule_id)
{
  	$(element).getForm().sendPhpr('index_onDeleteRule', 
	{
		confirm: 'Do you really want to delete this rule?',
		onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
		onComplete: LightLoadingIndicator.hide,
		update: 'rule_list_container',
		loadIndicator: {show: false},
		onFailure: popupAjaxError,
		extraFields: {'rule_id': rule_id},
		onAfterUpdate: rules_assign_sortables
	});
	
	return false;
}

window.addEvent('domready', function() {
	rules_assign_sortables();
});
