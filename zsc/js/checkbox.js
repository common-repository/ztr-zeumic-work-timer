var zsc = zsc || {};

(function() {
	var $ = jQuery;

	/**
	 * $('<input type="checkbox" data-default="false" data-checked="default"></input>').zscTriCheckbox(); is equivalent to:
	 * $('<input type="checkbox" />').zscTriCheckbox({default: false, checked: "default"});
	 * Note: Must be called on the checkbox AFTER it has been appended to the DOM.
	 * 
	 * @param {Object} [data]
	 * @param {bool|null|string} [data.checked = null] The initial state of the checkbox (true/"true", false/"false", or null/"null"/"default" for default).
	 * @param {bool} [data.default = false] Whether the default state should display as checked or unchecked.
	 * @param {function} [data.onUpdate] A callback for when the checkbox is updated. Passed a single parameter, the new value of checked.
	 * @param {string} [data.value] The checkbox's value, as it would be submitted in a form. Defaults to the element's value attribute.
	 */
	$.fn.zscTriCheckbox = function(data, arg1) {
		var _ = this;

		if (typeof data === "string") {
			if (data === 'update') {
				return update(_, arg1);
			}
			return _;
		}

		// Extend it with any new data
		data = $.extend(_.data(), data);
		
		if (data.default === true || data.default === "true") {
			data.default = true;
		} else {
			data.default = false;
		}

		data.value = data.value || _.val();

		// Hide the checkbox
		_.css('display', 'none');

		// Create display checkbox (which displays current state and can be clicked)
		var $display = $('<input type="checkbox" class="zsc-tri-checkbox" />');

		// When the checkbox is clicked, toggle through possible states
		$display.on('click', function() {
			var data = _.data();
			if (data.checked === null) {
				// So that when it is clicked with default, it will immediately override the default rather than
				// e.g. default is checked, clicking it keeps it checked. Best user experience.
				update(_, !data.default);
			} else if (data.checked === data.default) {
				update(_, null);
			} else {
				update(_, data.default);
			}
		})

		_.after($display);
		data.$display = $display;

		// Initialize the state of the checkbox
		update(_, data.checked, false);
		return _;
	}

	/**
	 * Set whether the checkbox is checked.
	 * @param {boolean} [callOnUpdate = true] Whether to call data.onUpdate.
	 */
	var update = function(_, checked, callOnUpdate) {
		if (callOnUpdate === undefined) {
			callOnUpdate = true;
		}

		var data = _.data();

		if (checked === true || checked === "true" || checked === 1 || checked === "1") {
			checked = true;
		} else if (checked === false || checked === "false" || checked === 0 || checked === "0") {
			checked = false;
		} else {
			checked = null;
		}

		data.checked = checked;

		data.$display.removeClass('checked');
		data.$display.removeClass('unchecked');
		data.$display.removeClass('default');

		if (checked === null) {
			data.$display.prop('checked', data.default);
			data.$display.addClass('default');

			// Make it so a form will send back "default"
			_.prop('checked', true);
			_.val("default");
		} else {
			data.$display.prop('checked', checked);
			data.$display.addClass(checked ? 'checked' : 'unchecked');

			// Make it so a form will sent back the value if checked, or nothing if unchecked
			_.prop('checked', checked);
			_.val(data.value);
		}

		if (callOnUpdate && data.onUpdate instanceof Function) {
			data.onUpdate(checked);
		}
		return _;
	};
})();

// When the document loads, convert any HTML checkboxes with data-default.
// E.g. <input type="checkbox" value="1" data-default="true" data-initial="default"></input> creates a checkbox with default checked state, and default as initial state.
jQuery(document).ready(function($) {
	$('input[type="checkbox"]').each(function() {
		if ($(this).data('default') === undefined) {
			return;
		}
		$(this).zscTriCheckbox();
	});
});
