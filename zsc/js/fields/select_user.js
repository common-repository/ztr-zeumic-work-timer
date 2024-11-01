var jsgrid_field_name;

(function() {
	if (typeof zsc === 'undefined') {
		return;
	}
	var $ = jQuery;

	/**
	 * Add select user field.
	 */
	var SelectUserField = function(config) {
		config = $.extend({
			/** The meta in zsc to get the users from. */
			metaKey: 'users',
			textField: 'name',
			valueField: 'id',
		}, config);

		// Add "no selection" to select
		var items = [{}];
		items[0][config.textField] = '';
		items[0][config.valueField] = 0;

		// Get items from zsc.meta, if metaKey provided
		items = items.concat(config.items || zsc.meta.get(config.metaKey));
		config.items = items;

		jsGrid.fields.select.call(this, config);
	};

	SelectUserField.prototype = new jsGrid.fields.select({
		align: "left",
		autosearch: true,

		itemTemplate: function(userId, item) {
			if (!userId) {
				return;
			}
			var display = jsGrid.fields.select.prototype.itemTemplate.call(this, userId, item);
			if (!display) {
				display = "(User doesn't exist or not in defined role for this column)";
			}
			return $('<a href="' + zsc.settings.adminUrl + 'user-edit.php?user_id=' + userId + '" title="' + display + '" target="_blank">' + display + '</a>');
		},
	});

	jsGrid.fields[jsgrid_field_name] = SelectUserField;
})();