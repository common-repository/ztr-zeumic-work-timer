var jsgrid_field_name;

(function() {
	var $ = jQuery;

	var ClientField = function (config) {
		var _ = this;

		jsGrid.fields.zsc_select_user.call(_, config);
	};

	ClientField.prototype = new jsGrid.fields.zsc_select_user({
		textField: 'name',
		valueField: 'id',

		editTemplate: function(value, item) {
			this.editing = !item.zwm_id;
			return jsGrid.fields.zsc_select_user.prototype.editTemplate.call(this, value);
		}
	});

	jsGrid.fields[jsgrid_field_name] = ClientField;
})();