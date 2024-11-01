var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;

	/**
	 * Similar to inbuilt select field, except automatically updates based on a ZSC meta.
	 */
	var BoundSelectField = function(config) {
		jsGrid.Field.call(this, config);
	};
	BoundSelectField.prototype = new jsGrid.SelectField({
		align: "left",
		items: [],
		valueField: 'id',
		textField: 'name',
		plugin: 'zsc', // Note that zsc.meta is the common meta
		metaKey: '',
		onChange: null,

		editTemplate: function(value, item) {
			var _ = this;
			if (!this.editing) {
				return this.itemTemplate(value, item);
			}

			var $editControl = _.extendedTemplate('editTemplate', value, item);
			if (this.onChange) {
				if (typeof this.onChange !== "function") {
					this.onChange = zsc.eval(_.onChange);
				}
				$editControl.on('change', function() {
					_.onChange.call(_, $(this).val(), item);
				});
			}
			return $editControl;
		},
		
		filterTemplate: function() {
			return this.extendedTemplate('filterTemplate');
		},

		insertTemplate: function() {
			if (!this.inserting) {
				return '';
			}

			return this.extendedTemplate('insertTemplate');
		},

		extendedTemplate: function(methodName, value, item) {
			// The first option should be a dummy blank option
			var blank = {};
			blank[this.valueField] = 0;
			blank[this.textField] = "";
			
			var meta = window[this.plugin].meta.get(this.metaKey) || [];
			if (!meta) {
				console.log(`${this.plugin}.meta.get('${this.metaKey}') does not exist`);
			}

			this.items = [blank].concat(meta);
			var _super = jsGrid.SelectField.prototype[methodName];

			var res = _super.call(this, value, item);
			var $select = $(res);

			var _this = this;
			window[this.plugin].meta.onUpdate(this.metaKey, function(new_templates) {
				_this.items = [{id: 0, name: ""}].concat(new_templates);

				// Keep insertControl and filterControl so that insertValue() and filterValue() still work properly
				var currentVal = $select.val();
				var filterControl = _this.filterControl;
				var insertControl = _this.insertControl;

				_this.filterControl = $select.empty().append($(_super.call(_this, value, item)).children());
				
				$select.val(currentVal);
				_this.filterControl = filterControl;
				_this.insertControl = insertControl;
			});
			return $select;
		},
	});

	jsGrid.fields[jsgrid_field_name] = BoundSelectField;
})();
