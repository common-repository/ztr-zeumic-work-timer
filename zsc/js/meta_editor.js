var zsc = zsc || {};

/**
 * @param {Object} [args]
 * @param {bool} [args.allowMove = true] Whether to allow moving up and down.
 * @param {string} [args.ctrlHeading = ""] Heading of the control column.
 * @param {string} [args.idField = "id"] Key of the id field in each object in input and output array. If applicable, will be returned as part of each entry when entries() is called.
 * @param {Object[]} [args.initial] array of default meta keys and values.
 * @param {string} [args.keyField = "key"] Key of the key field in each object in input and output array.
 * @param {string} [args.keyHeading = "Key"] Heading of the key column.
 * @param {Function} [args.onUpdate] Function to be called whenever the editor is updated (add, move, delete).
 * @param {string} [args.valueField = "value"] Key of the value field in each object in input and output array.
 * @param {string} [args.valueHeading = "Val"] Heading of the value column.
 */
zsc.MetaEditor = function(args) {
	var $ = jQuery; var _ = this;

	args = $.extend({
		allowMove: true,
		ctrlHeading: "",
		idField: "id",
		initial: [],
		keyField: "key",
		keyHeading: "Key",
		onUpdate: function() {},
		valueField: "value",
		valueHeading: "Val",
	}, args || {});

	_.$ = $('<div class="meta-editor"></div>'); // Removed table-responsive, review
	_.$.data('editor', _);

	_.allowMove = args.allowMove;
	_.idField = args.idField;
	_.keyField = args.keyField;
	_.onUpdate = args.onUpdate;
	_.valueField = args.valueField;

	_.$table = $('<table><thead><tr><th>' + args.keyHeading + '</th><th>' + args.valueHeading + '</th><th>' + args.ctrlHeading + '</th></tr></thead></table>');
	_.$.append(_.$table);
	
	for (var i = 0; i < args.initial.length; i++) {
		_.add(args.initial[i]);
	}
	
	var $btnAddContainer = $('<div style="float:left;"><table style="border:none;"><tr><td><input type="button" class="meta-button meta-add-button" style="padding:0px;" /></td></tr></table></div>');
	var $btnAdd = $btnAddContainer.find(".meta-add-button");
	
	$btnAdd.on("click", function () {
		_.add();
		_.onUpdate();
	});
	
	_.$.append($btnAddContainer);
}

zsc.MetaEditor.prototype = {
	add: function(entry) {
		var $ = jQuery; var _ = this;

		entry = entry || {};
		var key = entry[_.keyField] || "";
		var value = entry[_.valueField] || "";
		
		var $row = $('<tr class="meta-row"></tr>');
		if (entry[_.idField]) {
			$row.data('id', entry[_.idField]);
		}

		var $key = $('<td><textarea class="key">'+key+'</textarea></td>');
		$key.find('.key').data('prev', key); // Store this original key with the key cell
		var $value = $('<td><textarea class="value">'+value+'</textarea></td>');
		var $ctrl = $('<td></td>');
		
		var $btnDel = $('<input type="button" class="meta-button meta-delete-button" />');
		$ctrl.append($btnDel)
		if (_.allowMove) {
			var $btnMoveUp = $('<a style="float: right;" class="metamoveUp" href="javascript:void(0);"><span class="ui-icon ui-icon-triangle-1-n">&nbsp;</span></a>');
			var $btnMoveDown = $('<a style="float: right;" class="metamoveDown" href="javascript:void(0);"><span class="ui-icon ui-icon-triangle-1-s">&nbsp;</span></a>');
			
			$btnMoveUp.on('click', function() {
				$row.insertBefore($row.prev());
				_.onUpdate();
			});
			$btnMoveDown.on('click', function() {
				$row.insertAfter($row.next());
				_.onUpdate();
			});
			$ctrl.append($btnMoveUp).append('&nbsp;').append($btnMoveDown);
		}
		
		$row.append($key).append($value).append($ctrl);
		_.$table.append($row);
		
		$btnDel.on("click", function () {
			$(this).closest('tr').remove();
			_.onUpdate();
		});

		return _;
	},

	/**
	 * @param {Object} [args]
	 * @param {string|bool} [args.keyPrev = false] Whether to include previous key for each entry (if applicable), to tell whether the key has changed. Will be stored in entry.keyPrev, or entry[args.keyPrev] if string.
	 */
	entries: function(args) {
		var $ = jQuery; var _ = this;
		args = $.extend({
			keyPrev: false,
		}, args);
		if (args.keyPrev && typeof args.keyPrev !== "string") {
			args.keyPrev = "keyPrev";
		}
		
		var entries = [];
		this.$table.find("tr.meta-row").each(function() {
			var entry = {};
			entry[_.keyField] = $(this).find(".key").val();
			if (args.keyPrev) {
				entry[args.keyPrev] = $(this).find(".key").data('prev');
			}
			if ($(this).data('id')) {
				entry[_.idField] = $(this).data('id');
			}
			entry[_.valueField] = $(this).find(".value").val();
			entries.push(entry);
		});
		return entries;
	},
};
