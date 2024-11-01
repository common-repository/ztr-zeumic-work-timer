var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;

	var timer_start_stop = function(startOrStop, itemId) {
		return zwm.ajax({
			data: {
				action: 'zwm_timer_' + startOrStop,
				itemId: itemId,
			},
			success: function() {
				// Also reload ZTR if it is on the page
				if (typeof ztr !== 'undefined') {
					ztr.grid.loadData();
				}
			},
		});
	}

	var TimerField = function (config) {
		jsGrid.Field.call(this, config);
	};

	TimerField.prototype = new jsGrid.Field({
		editing: false,
		filtering: false,
		inserting: false,
		css: 'timer_field',
		align: 'center',
		itemTemplate: function(timing, item) {
			var $btn = $('<a href="javascript:void(0)" class="jsgrid-button"></a>');
			if (timing) {
				$btn.addClass('jsgrid-stoptimer-button');
				$btn.on('click', function(e) {
					e.stopPropagation();
					timer_start_stop('stop', item.id);
				});
			} else {
				$btn.addClass('jsgrid-timer-button');
				$btn.on('click', function(e) {
					e.stopPropagation();
					timer_start_stop('start', item.id);
				});
			}
			return $btn;
		},
	});

	jsGrid.fields[jsgrid_field_name] = TimerField;
})();