function make_extras_sortable()
{
	if ($('extra_options_list_body'))
		$('extra_options_list_body').makeListSortable('onSetExtraOrders', 'extra_order', 'extra_id', 'extra_sort_handle');
}

window.addEvent('domready', function(){
	make_extras_sortable();
});
