var ZTR;
(function() {
	var $ = jQuery;

	ZTR = function(settings) {
		zsc.Plugin.call(this, settings);
	}

	ZTR.prototype = Object.create(zsc.Plugin.prototype);
	$.extend(ZTR.prototype, {
		/* Begin overrides */

		init: function(args) {
			var _ = this;
			zsc.Plugin.prototype.init.call(_, args);

			// Keep totaltime updated.
			ztr.meta.onUpdate('totaltime', function(new_totaltime) {
				$('#ztr-total-time').html(new_totaltime);
			});
		},
	});
})();