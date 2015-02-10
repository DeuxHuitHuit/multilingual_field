(function ($, undefined) {

	'use strict';

	if (!!Symphony.Extensions.MultilingualField) {
		return;
	}
	Symphony.Extensions.MultilingualField = true;

	// from backend.views.js
	var change = function (e) {
		var selectbox = $(this);
		var parent = selectbox.parents('.instance');
		var headline = parent.find('.frame-header h4');
		var values = selectbox.find(':selected');
		var span = headline.find('.required');
		
		if(!!values.length) {
			var langs = [];
			values.each(function (index, elem) {
				var text = $(this).text();
				langs.push(text);
				if (index < values.length - 2) {
					langs.push(', ');
				} else if (index < values.length - 1) {
					langs.push(' and ');
				}
			});
			
			if (!span.length) {
				span = $('<span />', {
					class: 'required'
				}).appendTo(headline);
			}
			
			span.text(
				'— ' + langs.join('') + ' ' +
				Symphony.Language.get(langs.length > 1 ? 'are' : 'is') + ' ' +
				Symphony.Language.get('required')
			);
		}

		// Is not required
		else {
			headline.find('.required').remove();
		}
	};

	$(function () {
		$('.field-multilingual_textbox.instance select[name*="[required_languages]"]')
			.on('change', change)
			.trigger('change');
	});

})(jQuery);
