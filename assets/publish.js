/*-----------------------------------------------------------------------------
	Text Box Interface
-----------------------------------------------------------------------------*/
	
jQuery(document).ready(function() {
	jQuery('.field-multilingual').each(function() {
		var self = jQuery(this);
		var input = self.find('input, textarea');
		
		if (input.attr('length') < 1) return;
		
		var optional = self.find('i');
		var message = optional.text();
		
		var update = function() {
			var length = input.val().length;
			var limit = input.attr('length');
			var remaining = limit - length;
			
			optional
				.text(message.replace('$1', remaining).replace('$2', limit))
				.removeClass('invalid');
			
			if (remaining < 0) {
				optional.addClass('invalid');
			}
		};
		
		input.bind('blur', update);
		input.bind('change', update);
		input.bind('focus', update);
		input.bind('keypress', update);
		input.bind('keyup', update);
		
		update();
	});

	jQuery('.field-multilingual').each(function() {
		var field = new MultilingualField(jQuery(this));	
	});
});

function MultilingualField(field) {
	this.field = field;
	
	this.init();
}

MultilingualField.prototype.init = function() {
	var self = this;

	// bind tab events
	this.field.find('ul.tabs li').bind('click', function(e) {
		e.preventDefault();
		self.setActiveTab(jQuery(this).attr('class').split(' ')[0]);
	});
	
	// open the Map tab by default
	this.setActiveTab('es');
}

MultilingualField.prototype.setActiveTab = function(tab_name) {
	var self = this;
	
	// hide all tab panels
	this.field.find('.tab-panel').hide();
	
	// find the desired tab and activate the tab and its panel
	this.field.find('ul.tabs li').each(function() {
		var tab = jQuery(this);

		if (tab.hasClass(tab_name)) {
			tab.addClass('active');
			self.field.find('.tab-' + tab_name).show();
		} else {
			tab.removeClass('active');
		}
	});
}

/*---------------------------------------------------------------------------*/