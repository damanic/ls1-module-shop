function reviews_selected()
{
	return $('listShop_Reviews_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!reviews_selected())
	{
		alert('Please select reviews to delete.');
		return false;
	}
	
	$('listShop_Reviews_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected reviews(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'reviews_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function approve_selected()
{
	if (!reviews_selected())
	{
		alert('Please select reviews to approve.');
		return false;
	}
	
	$('listShop_Reviews_index_list_body').getForm().sendPhpr(
		'index_onApproveSelected',
		{
			confirm: 'Do you really want to approve selected reviews(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'reviews_page_content',
			onAfterUpdate: update_scrollable_toolbars
		}
	);
	return false;
}

function deselect_reviews()
{
	$('listShop_Reviews_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_uncheck();})
}

function select_reviews(e, select_type)
{
	var e = new Event(e);

	switch (select_type)
	{
		case 'all' :
			$('listShop_Reviews_index_list_body').getElements('tr td.checkbox input').each(function(element){element.cb_check();})
		break;
		case 'none' :
			deselect_reviews();
		break;
		case 'new' :
			if (!e.shift)
				deselect_reviews();

			$('listShop_Reviews_index_list_body').getElements('tr.review_new td.checkbox input').each(function(element){
				element.cb_check(); 
			})
		break;
		case 'approved' :
			if (!e.shift)
				deselect_reviews();

			$('listShop_Reviews_index_list_body').getElements('tr.review_approved td.checkbox input').each(function(element){
				element.cb_check();
			})
		break;
	}
	
	return false;
}