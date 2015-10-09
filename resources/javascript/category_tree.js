function fix_list_zebra(list)
{
	try
	{
		var items = $A(list.getChildren());
		items.each(function(item, index){
			if (index % 2)
				$(item).addClass('even');
			else
				$(item).removeClass('even');
		})
	} catch (e) {}
}

function init_category_sortables()
{
	var list = $('listShop_Categories_reorder_categories_list_body');
	if (list)
	{
		list.makeListSortable('reorder_categories_onSetOrders', 'category_order', 'category_id', 'row_icon.handle');
		list.addEvent('dragComplete', fix_list_zebra.pass(list));
	}
}

window.addEvent('domready', function(){
	var container = $('listShop_Categories_reorder_categories_list');
	if (container)
		container.addEvent('listUpdated', init_category_sortables);
		
	init_category_sortables();
})