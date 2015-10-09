var product_tabs;

window.addEvent('domready', function(){
	if ($('product_tabs'))
	{
		product_tabs = new TabManager('product_tabs', 
		  	'product_tab_pages', 
		  	{trackTab: true});
	}
})

function reviews_selected()
{
	return $('review_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function approve_selected_reviews()
{
	if (!reviews_selected())
	{
		alert('Please select reviews to approve.');
		return false;
	}

	$('reviews_form').sendPhpr(
		'preview_onApproveSelectedReviews',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'product_reviews_area',
			onFailure: popupAjaxError
		}
	);
	return false;
}

function delete_selected_reviews()
{
	if (!reviews_selected())
	{
		alert('Please select reviews to delete.');
		return false;
	}
	
	if (!confirm('Do you really want to delete selected review(s)?'))
		return false;

	$('reviews_form').sendPhpr(
		'preview_onDeleteSelectedReviews',
		{
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			update: 'product_reviews_area',
			onFailure: popupAjaxError
		}
	);
	return false;
}

if (window.unload_handlers == undefined)
	window.unload_handlers = [];

window.onbeforeunload = function() {
	var message = null;
	window.unload_handlers.some(function(hanlder){
		message = hanlder.call();
		if (message)
			return true;
	});

	if (message)
		return message;
}