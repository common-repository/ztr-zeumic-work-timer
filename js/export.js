jQuery(document).ready(function ($) {
	if ($("#ztr_export").length === 0) {
		return;
	}
	$("#ztr_export button.btn-export").click(function() {
		var users = $('#ztr_export select[name="users"]').val() || [];
		for (var i = 0; i < users.length; i++) {
			users[i] = Number(users[i]);
		}
		ztr_export.ajax({
			data: {
				action: 'ztr_export',
				filter: JSON.stringify({
					starttime: $('#ztr_export input[name="startdate"]').val(),
					endtime: $('#ztr_export input[name="enddate"]').val(),
					users: users,
				}),
			},
			loadData: false,
			success: function (resp, ts, xhr) {
				if (resp.url) {
					window.open(resp.url);
				}
			},
		})
	});
});
