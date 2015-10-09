CREATE TABLE `shop_company_information` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `address_contacts` text default NULL,
  `invoice_header_text` text default NULL,
  `invoice_footer_text` text default NULL,
  `invoice_template` varchar(50) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into shop_company_information(name, invoice_template) values ('Our Company', 'ls_standard');