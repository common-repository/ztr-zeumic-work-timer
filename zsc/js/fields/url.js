var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;
	/**
	 * Add URL field, for a text area that contains a URL.
	 */
	var URLField = function(config) {
		jsGrid.TextAreaField.call(this, config);
	};

	URLField.prototype = new jsGrid.TextAreaField({
		itemTemplate: function(url, item) {
			if (!url) {
				return '';
			}
			return $('<a href="' + url + '" target="_blank" title="' + url + '">' + url + '</a>');
		},
	});

	jsGrid.fields[jsgrid_field_name] = URLField;
})();