window.addEvent('domready', function(){
	$(document.getElement('html')).bindKeys({
		'meta+s, ctrl+s': save_item
	});
});

function save_item()
{
	if((window.PopupWindows.length > 0)) 
		return false;
	else 
	{
		var options = {
			prepareFunction: function(){phprTriggerSave();},
			extraFields: {redirect: 0},
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Saving...'),
			onComplete: LightLoadingIndicator.hide,
			update: 'multi'
		}
		$('form_element').sendPhpr('onSave', options);
		return false;
	}
}