window.addEvent('domready', function(){
	if (document.location.hash)
	{
		var hashValue = document.location.hash.substring(1);
		if (hashValue.test(/note_[0-9]+$/))
		{
			var note_id = hashValue.substring(5);
			new PopupForm('preview_onLoadNotePreview', {ajaxFields: {'note_id': note_id}});
		}
	}
	
	if ($('order_tabs'))
	{
		new TabManager('order_tabs', 
		  	'order_tab_pages', 
		  	{trackTab: true});
	}
})

function reply_to_note(note_id)
{
	cancelPopup();
	new PopupForm('preview_onLoadNoteForm', {ajaxFields: {'reply_note_id': note_id}});
	return false;
}

function refresh_invoice_list()
{
	$('order_invoice_list').getForm().sendPhpr(
		'preview_onUpdateInvoiceList',
		{
			update: 'order_invoice_list',
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				src: 'phproad/resources/images/form_load_30x30.gif',
				injectInElement: true,
				element: 'order_invoice_list'
			}
		}
	)
}