window.addEvent('domready', function(){
	if ($('customer_tabs'))
	{
		new TabManager('customer_tabs', 
		  	'customer_tab_pages', 
		  	{trackTab: false});
	}
})