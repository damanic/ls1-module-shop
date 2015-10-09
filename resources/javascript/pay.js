window.addEvent('domready', function(){
	var use_profile_cb = $('use_profile');
	if (use_profile_cb)
	{
		use_profile_cb.addEvent('click', function(){
			if (use_profile_cb.checked)
			{
				if ($('pay_from_profile_buttons'))
				{
					$('payment_form').hide();
					$('pay_from_profile_buttons').show();
				} else
				{
					$('payment_form').getElements('input').each(function(el){
						el.disabled = true;
						if (el.type == 'checkbox')
							el.cb_disable();
					})
				}
			}
			else
			{
				if ($('pay_from_profile_buttons'))
				{
					$('pay_from_profile_buttons').hide();
					$('payment_form').show();
				}
				else
					$('payment_form').getElements('input').each(function(el){
						el.disabled = false;
						if (el.type == 'checkbox')
							el.cb_enable();
					})
			}
		})
	}
});