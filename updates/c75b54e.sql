alter table shop_company_information add column shipping_label_template varchar(50) default 'ls_standard';
alter table shop_company_information add column shipping_label_labels_per_page integer(11) default 6;

alter table shop_company_information add column shipping_label_width float default '3.5';
alter table shop_company_information add column shipping_label_height float default '3';
alter table shop_company_information add column shipping_label_padding float default '0.2';
alter table shop_company_information add column shipping_label_css_units varchar(50) default 'in';
alter table shop_company_information add column shipping_label_font_size_factor float default '1.4';
alter table shop_company_information add column shipping_label_print_border tinyint(4) default 1;