var ColorPicker = new Class({
	Implements: [Options, Events],
	
	initialize: function(listElement, fieldElement)
	{
		this.listElement = $(listElement);
		this.fieldElement = $(fieldElement);
		
		this.listElement.getChildren().each(function(element){
			element.addEvent('click', this.colorClick.bind(this, element));
		}, this);
	},
	
	colorClick: function(element)
	{
		this.listElement.getChildren().each(function(li_element){
			li_element.removeClass('selected');
		}, this);

		element.addClass('selected');
		this.fieldElement.set('value', element.getFirst().getStyle('background-color'));
	}
});