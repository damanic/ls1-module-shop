CREATE TABLE `shop_shipping_boxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `params_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `width` float DEFAULT NULL,
  `length` float DEFAULT NULL,
  `depth` float DEFAULT NULL,
  `empty_weight` float DEFAULT NULL,
  `max_weight` float DEFAULT NULL,
  `inner_width` float DEFAULT NULL,
  `inner_length` float DEFAULT NULL,
  `inner_depth` float DEFAULT NULL,
  `volume` float DEFAULT NULL,
  PRIMARY KEY (`id`)
);
