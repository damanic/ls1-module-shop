<?php
$file_name = '_form_field_preview_text.htm';
$current_file = PATH_APP.'/phproad/modules/db/behaviors/db_formbehavior/partials/'.$file_name;
$replacement_file = PATH_APP.'/modules/shop/updates/phpr-patch_1.31.6/'.$file_name;
if(!copy($replacement_file, $current_file)){
	throw new Phpr_ApplicationException('Could not copy '.$file_name.' to '.$current_file.' check write permissions for PHP');
}