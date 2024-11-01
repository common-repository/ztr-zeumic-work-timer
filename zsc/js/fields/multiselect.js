var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;

	var MultiselectField = function (config) {
		jsGrid.Field.call(this, config);
	};

	/**
	 * @prop {Object[]} options {[this.textField]: string, [this.valueField]: string|number}.
	 * @prop {string} textField The key of each item in items to use as the display text.
	 * @prop {string} valueField The key of each item in items to use as the value.
	 */
	MultiselectField.prototype = new jsGrid.Field({
		align: 'left',
		autosearch: true,
		css: 'multiselect-field',
		options: [],
		placeholder: "",
		sortableOptions: false,
		textField: 'text',
		valueField: 'value',

		/**
		 * Get an option from its value.
		 */
		_getOption: function(value) {
			var _ = this;

			for (var i = 0; i < _.options.length; i++) {
				var option = _.options[i];

				if (_._getOptionValue(option) == value) {
					return option;
				}
			}

			return undefined;
		},

		/**
		 * Get the HTML that will display for the item when not editing the row.
		 * @return {string}
		 */
		_getOptionDisplay: function(option, i) {
			return this._getOptionText(option, i);
		},

		/**
		 * @return {string}
		 */
		_getOptionText: function(option, i) {
			return option[this.textField];
		},

		/**
		 * @return {string|number}
		 */
		_getOptionValue: function(option, i) {
			return option[this.valueField];
		},

		/**
		 * @return {Object[]}
		 */
		_getSelectedOptions: function($select) {
			var _ = this;
			var selectedValues = _._getSelectedValues($select);
			var selectedOptions = [];

			for (var i = 0; i < selectedValues.length; i++) {
				var value = selectedValues[i];

				var option = _._getOption(value);
				if (option) {
					selectedOptions.push(option);
				}
			}

			return selectedOptions;
		},

		/**
		 * @return {string[]}
		 */
		_getSelectedValues: function($select) {
			return $select.val() || [];
		},

		_makeControl: function(selected) {
			var $control = $('<span></span>');
			var $container = $('<span class="zsc-multiselect-container"></span>');
			$control.append($container);
			this._makeSelect($container, selected);

			return $control;
		},

		/**
		 * @param {jQuery} $container
		 * @param {(number|string)[]} selected
		 * @param {bool} sortable
		 */
		_makeSelect: function($container, selected) {
			var _ = this;

			var $select = $('<select class="select2-main"></select>');

			$container.append($select);

			$select.select2({
				data: $.map(_.options, function(option, i) {
					return {
						id: _._getOptionValue(option, i),
						text: _._getOptionText(option, i),
					};
				}),
				multiple: true,
				placeholder: _.placeholder,
				width: '100%',
			});

			if (_.sortableOptions) {
				zsc.select2Sortable($select, selected);
			} else {
				if (selected) {
					$select.val(selected).trigger('change');
				}
			}

			return $select;
		},

		editTemplate: function(selected, item) {
			if (!this.editing) {
				return this.itemTemplate(selected, item);
			}

			return this.editControl = this._makeControl(selected);
		},

		editValue: function() {
			return this._getSelectedValues(this.editControl.find('select'));
		},

		filterTemplate: function() {
			if (!this.filtering) {
				return '';
			}

			return this.filterControl = this._makeControl();
		},

		filterValue: function() {
			return this._getSelectedValues(this.filterControl.find('select'));
		},

		insertTemplate: function() {
			return this.insertControl = this._makeControl();
		},

		insertValue: function() {
			return this._getSelectedValues(this.insertControl.find('select'));
		},

		itemTemplate: function (selected, item) {
			var _ = this;

			if (!selected) {
				return '';
			}

			return $.map(selected, function(value, i) {
				var option = _._getOption(value);
				if (!option) {
					return null;
				}

				return _._getOptionDisplay(option, i);
			}).join(', ');
		},

		/**
		 * @deprecated since 10.3
		 */
		updateItems: function(newItems) {
			return this.updateOptions(newItems);
		},

		/**
		 * Update the field's options, and update all <select>s accordingly.
		 */
		updateOptions: function(newOptions) {
			this.options = newOptions || [];

			// Need to directly mutate, so that it correctly updates for all rows
			/*this.options.length = 0;
			for (var i = 0; i < newOptions.length; i++) {
				this.options.push(newOptions[i]);
			}*/

			if (this.editControl) {
				var editSelected = this.editValue();
				this._makeSelect(this.editControl.find('.zsc-multiselect-container').empty(), editSelected);
			}

			if (this.filterControl) {
				var filterSelected = this.filterValue();
				this._makeSelect(this.filterControl.find('.zsc-multiselect-container').empty(), filterSelected);
			}

			if (this.insertControl) {
				var insertSelected = this.insertValue();
				this._makeSelect(this.insertControl.find('.zsc-multiselect-container').empty(), insertSelected);
			}
		}
	});

	jsGrid.fields[jsgrid_field_name] = MultiselectField;
})();