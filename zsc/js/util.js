var zsc = zsc || {};

(function() {
	var $ = jQuery;

	/**
	 * Make an AJAX request.
	 * @param {Object} config
	 * @param {Object} config.data
	 * @param {function} config.success
	 */
	zsc.ajax = function(config) {
		config = $.extend({
			data: {},
			error: null,
			success: null,
		}, config);

		return $.ajax({
			type: 'POST',
			url: zsc.settings.ajaxUrl,
			data: config.data,
			dataType: 'json',
			error: function(xhr, textStatus, errorThrown) {
				if (config.error) {
					config.error(xhr, textStatus, errorThrown);
				}
				zsc.ajaxError(xhr, textStatus, errorThrown);
			},
			success: config.success,
		});
	},

	zsc.ajaxError = function(xhr, textStatus, errorThrown) {
		if (xhr.responseText) {
			alert(xhr.responseText);
		} else if (xhr.status === 401) {
			alert("You do not have permission to perform the requested action. Try logging in as a different user.");
		} else if (xhr.status === 403) {
			alert("The requested action is forbidden.");
		} else if (xhr.status === 400) {
			alert("Your input was invalid. Please check your input and try again.");
		} else if (xhr.status === 500) {
			alert("There was an internal server error. Please try again later.");
		} else {
			alert("There was an unknown error code: " + xhr.status);
		}
	};

	/**
	 * Create a thickbox.
	 * add_thickbox() must have been called in WP to use this method.
	 */
	zsc.createThickbox = function(url, txt) {
		var tb = $('<a href="#" class="thickbox">' + txt + '</a>');
		tb.on('mouseover', function(e) {
			var width = Math.floor($(window).width() * 0.8);
			var height = Math.floor($(window).height() * 0.8);
			$(this).attr('href', url + "&TB_iframe=true&width=" + width + "&height=" + height);
		});
		return tb;
	};

	/**
	 * Enable autosearch on an input element, so that pressing enter with it focused will search 
	 */
	zsc.enableAutosearch = function($el, grid) {
		$el.on("keypress", function(e) {
			if(e.which === 13) {
				grid.search();
				e.preventDefault();
			}
		});
		return $el;
	}

	zsc.escapeHtml = function(text) {
		'use strict';
		if (!text) {
			return '';
		}
		text = text.toString();
		return text.replace(/[\"&'\/<>]/g, function (a) {
			return {
				'"': '&quot;', '&': '&amp;', "'": '&#39;',
				'/': '&#47;',  '<': '&lt;',  '>': '&gt;'
		}[a];
		});
	};

	/**
	 * Eval a string into a proper JS object, or just return it if it's not a string.
	 */
	zsc.eval = function(source) {
		if (typeof source !== "string") {
			return source;
		}
		var x;
		eval("x = " + source);
		return x;
	};

	/**
	 * Find the first object in an array with given property values. [{id: _, key: _, value: _, meta: _}, ...]
	 * E.g. findObjectInArray([{id: 1, key: 'test', value: 2}, {id: 1, key: 'hi', value: 3}, {key: 'hi', value: 4}], {key: 'hi', value: 3}) returns {id: 1, key: 'hi', value: 3}.
	 * @param {Object[]} array
	 * @param {Object} props
	 * @return {Object|undefined} The object if found, undefined otherwise.
	 */
	zsc.findObjectInArray = function(array, props) {
		mainLoop: for (var i = 0; i < array.length; i++) {
			var item = array[i];
			for (var prop in props) {
				var propValue = props[prop];
				if (!item.hasOwnProperty(prop)) {
					continue mainLoop;
				}
				if (propValue !== item[prop]) {
					continue mainLoop;
				}
			}
			return item;
		}
		return;
	};

	/**
	 * Test whether the given string is a URL.
	 * @param {string} str
	 * @return {boolean}
	 */
	zsc.isUrl = function(str) {
		var urlRegex = '^(?!mailto:)(?:(?:http|https|ftp)://)(?:\\S+(?::\\S*)?@)?(?:(?:(?:[1-9]\\d?|1\\d\\d|2[01]\\d|22[0-3])(?:\\.(?:1?\\d{1,2}|2[0-4]\\d|25[0-5])){2}(?:\\.(?:[0-9]\\d?|1\\d\\d|2[0-4]\\d|25[0-4]))|(?:(?:[a-z\\u00a1-\\uffff0-9]+-?)*[a-z\\u00a1-\\uffff0-9]+)(?:\\.(?:[a-z\\u00a1-\\uffff0-9]+-?)*[a-z\\u00a1-\\uffff0-9]+)*(?:\\.(?:[a-z\\u00a1-\\uffff]{2,})))|localhost)(?::\\d{2,5})?(?:(/|\\?|#)[^\\s]*)?$';
    	var url = new RegExp(urlRegex, 'i');
    	return str.length < 2083 && url.test(str);
	}

	/**
	 * Perform a given function on any elements which match the given selectors, when they are added to the DOM or if they are currently there.
	 * @param {string|jQuery} parentSelector: the selector for the parent of the element (set 'body' if not sure)
	 * @param {string} selector: the specific selector for the elements to match
	 * @param {function} func: the func to perform, taking the jQuery object of the selection as the only argument
	 * 
	 */
	zsc.onCreate = function(parentSelector, selector, func) {
		func($(parentSelector).find(selector));
		var MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
		var mainObserver = new MutationObserver(function (mutationRecords) {
			mutationRecords.forEach(function(mutation) {
				if (typeof mutation.addedNodes === "object" && mutation.addedNodes.length !== 0) {
					var $jq = $(mutation.addedNodes);
					if ($jq.is(selector)) {
						func($jq);
					}
					// Want to use $jq.find(selector), but for some reason this does not work, at least in WP's version of jQuery
					var matched = $jq.find('*').filter(selector);
					if (matched.length > 0) {
						func(matched);
					}
				}
			});
		});
		$(parentSelector).each(function() {
			mainObserver.observe(this, {childList: true, characterData: false, attributes: false, subtree: true});
		});
	};

	/**
	 * Perform a given function on any elements which match the given selectors, when they are removed from the DOM.
	 * @param {string|jQuery} parentSelector: the selector for the parent of the element (set 'body' if not sure)
	 * @param {string|jQuery} selector: the specific selector for the elements to match
	 * @param {function} func: the func to perform, taking the jQuery object of the selection as the only argument
	 * 
	 */
	zsc.onRemove = function(parentSelector, selector, func) {
		var MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
		var mainObserver = new MutationObserver(function (mutationRecords) {
			mutationRecords.forEach(function(mutation) {
				if (typeof mutation.removedNodes === "object" && mutation.removedNodes.length !== 0) {
					var $jq = $(mutation.removedNodes);
					if ($jq.is(selector)) {
						func($jq);
					}
					var matched = $jq.find('*').filter(selector);
					if (matched.length > 0) {
						func(matched);
					}
				}
			});
		});
		$(parentSelector).each(function() {
			mainObserver.observe(this, {childList: true, characterData: false, attributes: false, subtree: true});
		});
	};

	/**
	 * Get an attribute of a product from zsc.meta.get('products').
	 * @param {number} product_id
	 * @param {string} key
	 * @return {any} The value of the attribute, or undefined.
	 */
	zsc.productGet = function(product_id, key) {
		var products = zsc.meta.get('products');
		if (!products) {
			return;
		}
		var product = products[product_id];
		if (!product) {
			return;
		}
		return product[key];
	};

	/**
	 * Update the <option>s of a select2 to match the given selection order, by moving all given values to the top.
	 * 
	 * @param {jQuery} $select2 A <select> element which has had select2() called on it.
	 * @return {jQuery}
	 */
	zsc.select2FixOrder = function($select2, selected) {
		if (!selected) {
			return $select2;
		}

		for (var i = selected.length - 1; i >= 0; i--) {
			var value = selected[i];

			// Find the corresponding <option> in the actual <select> and move it to the end.
			// If we do this for all selected options, the result will be that the <option>s are now sorted in
			// the correct order, and $select2.val() will return the correct result.
			$select2.find('option').each(function() {
				if ($(this).val() == value) {
					$(this).prependTo($(this).parent());
					return false;
				}
			});
		}

		return $select2;
	};

	/**
	 * Make a select2 v4 select sortable.
	 * 
	 * @param {jQuery} $select2 A <select> element which has had select2() called on it.
	 * @param {string[]} [selected] If provided, will initialize the select2 with this selection.
	 * @return {jQuery}
	 */
	zsc.select2Sortable = function($select2, selected) {
		var select2 = $select2.data('select2');

		select2.$selection.find('ul').sortable({
			update: function() {
				var newSelectionOrder = [];

				// After updating, we need to reorder the <option>s in the <select> based on the new ordering.
				// Otherwise $select2.val() will not return the correct result.
				select2.$selection.find('li').each(function() {
					var data = $(this).data('data');
					if (!data) {
						return;
					}
					newSelectionOrder.push(data.id);
				});

				zsc.select2FixOrder($select2, newSelectionOrder);

				// We also need to manually trigger a change event.
				$select2.trigger('change');
			},
		});

		if (selected) {
			zsc.select2FixOrder($select2, selected);
			$select2.val(selected).trigger('change');
		}

		return $select2;
	};

	/**
	 * @param {Object[]} options {id: string|number, text: string}[]
	 */
	zsc.updateSelectOptions = function($select, options) {
		var selected = $select.val();

		$select.empty();
		
		for (var i = 0; i < options.length; i++) {
			$select.append('<option value="' + options[i].id + '">' + options[i].text + '</option>');
		}

		$select.val(selected);
	};

	/**
	 * Get a user object by their ID.
	 */
	zsc.user = function(id) {
		id = Number(id);
		var users = zsc.meta.get('users') || [];
		for (var i = 0; i < users.length; i++) {
			if (users[i].id === id) {
				return users[i];
			}
		}
		return null;
	};
})();

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;

	// jsGrid by default does not escape HTML
	jsGrid.Field.prototype.itemTemplate = zsc.escapeHtml;

	// Override updateRow so that it's not deeply recursive
	// (doesn't merge each field's new value with its old value, resulting in unexpected results for objects and especially arrays)
	// Based on 1.5.3 base implementation
	jsGrid.Grid.prototype._updateRow = function($updatingRow, editedItem) {
		var updatingItem = $updatingRow.data("JSGridItem"), // JSGRID_ROW_DATA_KEY in jsGrid, but that's private, so we have to hardcore "JSGridItem"
			updatingItemIndex = this._itemIndex(updatingItem),
			updatedItem = $.extend({}, updatingItem, editedItem); // Changed from $.extend(true, {}, updatingItem, editedItem);

		var args = this._callEventHandler(this.onItemUpdating, {
			row: $updatingRow,
			item: updatedItem,
			itemIndex: updatingItemIndex,
			previousItem: updatingItem
		});

		return this._controllerCall("updateItem", updatedItem, args.cancel, function(loadedUpdatedItem) {
			var previousItem = $.extend(true, {}, updatingItem);
			updatedItem = loadedUpdatedItem || $.extend(updatingItem, editedItem);

			var $updatedRow = this._finishUpdate($updatingRow, updatedItem, updatingItemIndex);

			this._callEventHandler(this.onItemUpdated, {
				row: $updatedRow,
				item: updatedItem,
				itemIndex: updatingItemIndex,
				previousItem: previousItem
			});
		});
	};
})();

(function() {
	var $ = jQuery;

	/**
	 * Create a popup menu, used by ZWM Pro's delay button and ZPR Pro's create button.
	 */
	zsc.PopupMenu = function(id, btnSelector) {
		var _ = this;
		var $ = jQuery;
		_.$ = $('<div id="' + id + '" class="zsc-dropdown"></div>');
		_.$.hide();
		// Hide the menu when it is clicked off
		$(window).on('click', function(e) {
			if (!$(e.target).is(id) && !$(e.target).parents(id).length && !(btnSelector && $(e.target).is(btnSelector))) {
				_.$.hide();
			}
		});
		$('body').append(_.$);
	}

	zsc.PopupMenu.prototype = {
		/**
		 * Add an option to a popup menu.
		 */
		add_option: function(text, onclick) {
			var $ = jQuery; var _ = this;
			var $option = $('<span>' + text + '</span>');
			$option.on('click', function() {
				_.$.hide();
				onclick.call(this);
			});
			_.$.append($option);
			return $option;
		},

		empty: function() {
			this.$.empty();
		},
	};
})();