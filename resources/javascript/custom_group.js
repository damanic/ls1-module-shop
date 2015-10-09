function make_products_sortable()
{
	if ($('group_products_list'))
	{
		$('group_products_list').makeListSortable('onSetOrders', 'product_order', 'product_id', 'sort_handle');
		$('group_products_list').addEvent('dragComplete', fix_orders);
	}
}

function fix_orders(sortable_list_orders)
{
	$('group_products_list').getChildren().each(function(element, index){
		var order_input = element.getElement('input.product_order');
		if (order_input)
		{
			if (index <= sortable_list_orders.length-1)
				order_input.value = sortable_list_orders[index];
		}
		
		if (index % 2)
			element.addClass('even');
		else
			element.removeClass('even');
	})
}

window.addEvent('domready', function(){
	if ($('group_products_list'))
		make_products_sortable();
})