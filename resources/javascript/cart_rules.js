function assign_coupon_handler()
{
	var list = $('Shop_CartPriceRule_coupon_id');
	if (list)
	{
		list.addEvent('change', function(){
			if (list.get('value') == -1)
				new PopupForm('onLoadAddCouponForm');
		})
	}
}

window.addEvent('domready', function(){
	assign_coupon_handler();
});