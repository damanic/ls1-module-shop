window.addEvent('domready', function(){
	var groups_slide = new Fx.Slide('group_list');
	if ($('csv_importShop_CustomerCsvImportModel_auto_create_groups').checked)
		groups_slide.hide();
	
	$('csv_importShop_CustomerCsvImportModel_auto_create_groups').addEvent('click', function(){
		if (!$('csv_importShop_CustomerCsvImportModel_auto_create_groups').checked)
		{
			groups_slide.slideIn().chain(function(){
				$('group_list').getParent().addClass('allow-overflow');
			});
		} else 
		{
			$('group_list').getParent().removeClass('allow-overflow');
			groups_slide.slideOut();
		}
	});
})