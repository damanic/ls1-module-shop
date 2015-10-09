update shop_option_matrix_records set options_hash=(
  select 
    md5(group_concat(concat(md5(name), '-', shop_option_matrix_options.option_value) order by 1 separator '|'))
  from 
    shop_custom_attributes, 
    shop_option_matrix_options 
  where 
    matrix_record_id= shop_option_matrix_records.id
    and shop_option_matrix_options.option_id=shop_custom_attributes.id
  );