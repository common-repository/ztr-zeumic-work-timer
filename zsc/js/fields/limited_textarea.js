var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;
	/**
	 * Add limited text area field, for a text area with a character limit.
	 */
	var LimitedTextAreaField = function(config) {
		jsGrid.TextAreaField.call(this, config);
	};

	LimitedTextAreaField.prototype = new jsGrid.TextAreaField({
		editTemplate: function(value) {
			this.editControl = $("<textarea>");
			var maxlength = this.maxlength || 0;
			if (this.maxlength)
				this.editControl.attr("maxlength", this.maxlength);
			this.editControl.attr("value", value);
			this.editControl.val(value);
			return this.editControl;
		},
		editValue: function(value) {
			return this.editControl.val();
		},
	});

	jsGrid.fields[jsgrid_field_name] = LimitedTextAreaField;
})();