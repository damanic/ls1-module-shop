function products_selected()
{
	return $('listtop_products_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function remove_selected_products()
{
	if (!products_selected())
	{
		alert('Please select products to remove.');
		return false;
	}
	
	$('listtop_products_list_body').getForm().sendPhpr(
		'manage_top_products_onRemoveSelected',
		{
			confirm: 'Do you really want to remove selected product(s) from the category top products?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'listtop_products_list',
			onAfterUpdate: make_top_sortable
		}
	);
	return false;
}

function make_top_sortable()
{
	if ($('listtop_products_list_body'))
	{
		$('listtop_products_list_body').makeListSortable('manage_top_products_onSetOrders', 'product_order', 'product_id', 'sort_handle');
		$('listtop_products_list_body').addEvent('dragComplete', fix_group_zebra)
	}
}

function fix_group_zebra()
{
	$('listtop_products_list_body').getChildren().each(function(element, index){
		if (index % 2)
			element.addClass('even');
		else
			element.removeClass('even');
	})
}

window.addEvent('domready', function(){
	if ($('listtop_products_list'))
	{
		$('listtop_products_list').addEvent('listUpdated', make_top_sortable)
		make_top_sortable();
	}
})