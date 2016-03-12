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
				langs.push(text.split(' ||')[0]);
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

		if (!!Symphony.Context.get().version) {
			Symphony.Elements.contents.find('.instance.field-textbox').each(function () {
				var $field = $(this);
				var $header = $field.find('.frame-header');
				$header.append(
					$('<a />').attr('class', 'field-multilingual_textbox-converter debug')
						.attr('style', 'right: 11rem;font-size: 0.9em;')
						.text(Symphony.Language.get('Convert to multilingual'))
				);
			}).on('click', '.field-multilingual_textbox-converter', function (e) {
				var $field = $(this).closest('.field-textbox');
				var id = $field.find('input[name$=\\[id\\]]').val();
				
				e.stopPropagation();
				if (!confirm(Symphony.Language.get('Are you sure?'))) {
					return false;
				}
				
				$.post(Symphony.Context.get('path') + '/extension/multilingual_field/convert/' + id + '/')
					.done(function (data) {
						if (data && data.ok) {
							window.location.reload();
						}
						else if (data.error) {
							alert(data.error);
						}
						else {
							alert('Unknown error.');
						}
					});
				return;
			});
		}
	});

})(jQuery);
