var jsgrid_field_name;

(function() {
	var $ = jQuery;

	var MultiselectUserField = function (config) {
		var _ = this;

		jsGrid.fields.zsc_multiselect.call(_, config);

		zsc.meta.onUpdate('users', function(users) {
			_.updateOptions(users);
		}, true);
	};

	MultiselectUserField.prototype = new jsGrid.fields.zsc_multiselect({
		textField: 'name',
		valueField: 'id',

		_getOptionDisplay: function(user) {
			return '<a href="' + zsc.settings.adminUrl + 'user-edit.php?user_id=' + user.id + '">' + this._getOptionText(user) + '</a>';
		},
	});

	jsGrid.fields[jsgrid_field_name] = MultiselectUserField;
})();