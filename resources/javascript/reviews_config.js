function update_multi_review_message_state(cb)
{
	if (cb.checked)
		$('Shop_ReviewsConfiguration_duplicate_review_message').disabled = false;
	else
		$('Shop_ReviewsConfiguration_duplicate_review_message').disabled = true;
}

window.addEvent('domready', function(){
	var cb = $('Shop_ReviewsConfiguration_no_duplicate_reviews');
	if (cb)
	{
		update_multi_review_message_state(cb);
		cb.addEvent('click', update_multi_review_message_state.pass(cb));
	}
});