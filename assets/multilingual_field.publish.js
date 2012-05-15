;(function($, undefined){

	$(document).ready(function() {
		$('div.field-multilingual-textbox').each(function() {
			var $field = $(this);

			var update = function(e) {
				if( e.data.msg.indexOf('$1') !== -1  ){
					var length = e.data._input.val().length;
					var limit = e.data._input.attr('length');
					var remaining = limit - length;

					e.data._opt
						.text(e.data.msg.replace('$1', remaining).replace('$2', limit))
						.removeClass('invalid');

					if (remaining < 0) {
						e.data._opt.addClass('invalid');
					}
				}
			};

			$field.find('i').each(function(){
				var $_opt = $(this);
				var lang_code = $_opt.data('lang_code');

				var msg = $_opt.text();
				var $_input = $field.find('.tab-panel.tab-'+lang_code).find('input, textarea');
				var data = {_opt: $_opt, _input: $_input, msg: msg};

				$_input.on('blur change focus keypress keyup', data, update);

				update({data:data});
			});
		});
	});

})(this.jQuery);
