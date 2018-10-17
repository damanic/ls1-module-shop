var images_uploader = null;

function on_product_file_uploaded(file_column_name)
{
	if (file_column_name == 'images_file')
	{
		var height = $('images_controls').getScrollSize().y;
		$('images_controls').getParent().setStyle('height', height + 'px');
		product_import_reposition_images();
	}
}

function product_import_reposition_images()
{
	if (images_uploader)
		images_uploader.reposition();
}

function set_uploader_visibility(visible)
{
	var el = $('csv_importform_uploader_container_Shop_ProductCsvImportModel_images_file');
	if(el){
		var obj = el.getElement('object');
		if (obj)
		{
			if (!visible)
				obj.setStyle('display', 'none');
			else
				obj.setStyle('display', 'block');
		}
	}

}

window.addEvent('phpr_file_upload_loaded', function(column_name, uploader){
	if (column_name == 'images_file')
	{
		images_uploader = uploader;
		
		if (!$('csv_importShop_ProductCsvImportModel_import_product_images').checked)
			set_uploader_visibility(false);
	}
});

window.addEvent('domready', function(){
	
	var categories_slide = new Fx.Slide('category_list');
	
	if ($('csv_importShop_ProductCsvImportModel_auto_create_categories').checked)
	{
		$('category_list').addClass('hidden_slide');
		categories_slide.hide();
	}
	
	var product_types_slide = new Fx.Slide('product_type_list');
	if ($('csv_importShop_ProductCsvImportModel_auto_product_types').checked)
	{
		$('product_type_list').addClass('hidden_slide');
		product_types_slide.hide();
	}
	
	var product_groups_slide = new Fx.Slide('product_group_list');
	if ($('csv_importShop_ProductCsvImportModel_auto_create_product_groups').checked)
	{
		$('product_group_list').addClass('hidden_slide');
		product_groups_slide.hide();
	} 
	
	var images_slide = new Fx.Slide('images_controls');
	images_slide.hide();
	set_uploader_visibility(false);
	
	var tax_class_slide = new Fx.Slide('tax_class_list');
	if ($('csv_importShop_ProductCsvImportModel_auto_tax_classes').checked)
		tax_class_slide.hide();
	
	var manufacturer_slide = new Fx.Slide('manufacturer_list');
	if ($('csv_importShop_ProductCsvImportModel_auto_manufacturers').checked)
		manufacturer_slide.hide();
		
	var files_slide = new Fx.Slide('files_controls');
	files_slide.hide();
	
	$('csv_importShop_ProductCsvImportModel_auto_product_types').addEvent('click', function(){
		if ($('csv_importShop_ProductCsvImportModel_auto_product_types').checked)
		{
			$('product_type_list').getParent().removeClass('allow-overflow');
			product_types_slide.slideOut();
		}
		else
		{
			product_types_slide.slideIn().chain(function(){
				$('product_type_list').getParent().addClass('allow-overflow');
			});
		}
	});
	$('product_type_list').getParent().addClass('allow-overflow');

	$('csv_importShop_ProductCsvImportModel_auto_create_categories').addEvent('click', function(){
		if (!$('csv_importShop_ProductCsvImportModel_auto_create_categories').checked)
		{
			categories_slide.slideIn().chain(function(){
				$('category_list').removeClass('hidden_slide');
			});
		} else 
		{
			$('category_list').addClass('hidden_slide');
			categories_slide.slideOut();
		}
	});
	
	$('csv_importShop_ProductCsvImportModel_import_product_images').addEvent('click', function(){
		if ($('csv_importShop_ProductCsvImportModel_import_product_images').checked)
		{
			images_slide.slideIn();
			product_import_reposition_images();
			set_uploader_visibility(true);
		}
		else 
		{
			images_slide.slideOut();
			set_uploader_visibility(false);
		}
	});
	
	$('csv_importShop_ProductCsvImportModel_auto_tax_classes').addEvent('click', function(){
		if ($('csv_importShop_ProductCsvImportModel_auto_tax_classes').checked)
		{
			$('tax_class_list').getParent().removeClass('allow-overflow');
			tax_class_slide.slideOut();
		}
		else 
			tax_class_slide.slideIn().chain(function(){
				$('tax_class_list').getParent().addClass('allow-overflow');
			});
	});

	$('csv_importShop_ProductCsvImportModel_auto_manufacturers').addEvent('click', function(){
		if ($('csv_importShop_ProductCsvImportModel_auto_manufacturers').checked)
		{
			$('manufacturer_list').getParent().removeClass('allow-overflow');
			manufacturer_slide.slideOut();
		}
		else 
			manufacturer_slide.slideIn().chain(function(){
				$('manufacturer_list').getParent().addClass('allow-overflow');
			});
	});
	
	$('csv_importShop_ProductCsvImportModel_import_product_files').addEvent('click', function(){
		if (!$('csv_importShop_ProductCsvImportModel_import_product_files').checked)
			files_slide.slideOut();
		else 
			files_slide.slideIn();
	});
	
	$('csv_importShop_ProductCsvImportModel_auto_create_product_groups').addEvent('click', function(){
		if (!$('csv_importShop_ProductCsvImportModel_auto_create_product_groups').checked)
		{
			product_groups_slide.slideIn().chain(function(){
				$('product_group_list').removeClass('hidden_slide');
			});
		} else 
		{
			$('product_group_list').addClass('hidden_slide');
			product_groups_slide.slideOut();
		}
	});
	
	window.addEvent('phpr_file_upload_complete', on_product_file_uploaded);
})