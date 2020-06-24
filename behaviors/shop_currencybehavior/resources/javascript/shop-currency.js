(function ($) {

	var shopCurrencyBehaviour = {

		initEvents : function () {
			var _this = this;
			_this.$container = $('form');
			_this.$container.on('click', '.shop-currency__clicker', function (e) {
				e.preventDefault();
				let $el = $(this);
				let $sessionKeyInput = $('input[name=edit_session_key]');
				let sessionKey = $sessionKeyInput.length ? $sessionKeyInput.val() : null;
				let modelClass = $el.data('model-class');
				let modelID = $el.data('model-id');
				let modelField = $el.data('model-field');
				let $fieldInput = $('#'+modelClass+'_'+modelField);
				let fieldValue = $fieldInput.length ? $fieldInput.val() : null;
				new PopupForm('onLoadPriceFieldPopup', {
					ajaxFields: {
						'edit_session_key': sessionKey,
						'master_object_id' : modelID,
						'master_object_class' : modelClass,
						'field' : modelField,
						'field_value' : fieldValue
					}
				});
			});
		}
	}


	function initEvents() {
		shopCurrencyBehaviour.initEvents();
	}

	$(document).ready(function () {
		initEvents();
	});


})(jQuery);
