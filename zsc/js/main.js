var zsc = zsc || {};

(function() {
	var $ = jQuery;

	zsc.meta = new zsc.Meta();
	zsc.settings = {
		adminUrl: "",
		ajaxUrl: "",
		/** The date format (e.g. "dd/mm/yy") to use for datepicker elements. */
		dateFormat: null,
	};

	zsc.Plugin = function(settings) {
		var _ = this;

		_.settings = $.extend({
			/** The focal element of the plugin. */
			$: null,
			addRefreshBtn: false,
			autoload: true,
			common: {},
			editing: true,
			/** Fields for jsGrid. If empty, the grid will not be loaded at all. */
			fields: [],
			filtering: true,
			inserting: true,
			maintainSorting: true,
			meta: {},
			numRows: 5,
			sorting: true,
			defaultSortField: null,
			defaultSortOrder: null,
			ticker: 'zsc',
		}, settings || {});

		$.extend(zsc.settings, _.settings.common);
		_.settings.common = zsc.settings;

		_.meta = new zsc.Meta(zsc.meta);
		_.meta.update(_.settings.meta);

		jQuery(document).ready(function($) {
			_.init();
		});
	}

	zsc.Plugin.prototype = {
		init: function() {
			var _ = this;

			_.$ = $(_.settings.$);
			if (_.$.length === 0) {
				_.$ = null;
			}
			_.adminUrl = _.settings.common.adminUrl;
			_.ajaxUrl = _.settings.common.ajaxUrl;
			_.ticker = _.settings.ticker;

			// (Possibly) init the grid
			if (_.settings.fields.length > 0) {
				var funcs = ['headerTemplate', 'itemTemplate', 'filterTemplate', 'insertTemplate', 'editTemplate', 'filterValue', 'insertValue', 'editValue', 'cellRenderer'];
				for (var i = 0; i < _.settings.fields.length; i++) {
					var field = _.settings.fields[i];
					
					for (var f = 0; f < funcs.length; f++) {
						var func = funcs[f];
						if (func in field) {
							field[func] = zsc.eval(field[func]);
						}
					}
				}

				// Add a refresh button to the start of the control field's filter.
				if (_.settings.addRefreshBtn) {
					var control_field = _.getField('control', _.settings.fields);
					if (control_field) {
						var oldFilterTemplate = jsGrid.fields.control.prototype.filterTemplate;
						if (control_field.hasOwnProperty('filterTemplate')) {
							oldFilterTemplate = control_field.filterTemplate;
						}
						control_field.filterTemplate = function() {
							var $result = oldFilterTemplate.apply(this, arguments);
							
							var grid = this._grid;
							
							var $btnRefresh = jQuery('<input class="jsgrid-button zsc-refresh-btn" type="button" title="Refresh the table, maintaining current view" />')
							$btnRefresh.on('click', function() {
								grid.loadData();
							});
							
							$result = $btnRefresh.add($result);
							return $result;
						}
					}
				}

				// Initialize the grid
				var gridConfig = {
					width: '100%',
					height: 'auto',
					autoload: false, // If we use jsGrid's inbuilt autoload we cannot implement default sort field, so set this to false
					paging: true,
					pageLoading: true,
					pageSize: _.settings.numRows,
					pageIndex: 1,
					editing: _.settings.editing,
					inserting: _.settings.inserting,
					sorting: _.settings.sorting,
					filtering: _.settings.filtering,
					loadIndication: true,
					fields: _.settings.fields,

					onInit: function(args) {
						_.grid = args.grid;
						if (_.settings.maintainSorting) {
							// The same as the default, except without the this._resetSorting() line
							_.grid.search = function(filter) {
								this._resetPager();
								return this.loadData(filter);
							};
						}
					},
					onDataLoaded: function(args) {
						return _.onDataLoaded(args);
					},
					controller: {
						loadData: function(filter) {
							return _.controllerLoadData(filter);
						},
						insertItem: function(item) {
							return _.controllerInsertItem(item);
						},
						updateItem: function(item) {
							return _.controllerUpdateItem(item);
						},
						deleteItem: function(item) {
							return _.controllerDeleteItem(item);
						},
					},
				};
				_.$.jsGrid(gridConfig);

				if (_.settings.autoload) {
					// Initial load and sort (for Pro), or normal load (for free)
					if (_.settings.defaultSortField) {
						_.grid.sort(_.settings.defaultSortField, _.settings.defaultSortOrder);
					} else {
						_.grid.loadData();
					}
				}
			}

			if ($.prototype.datepicker) {
				// Make any .datepicker elements into actual datepickers.
				_.onCreate('.datepicker input, input.datepicker', function($el) {
					var config = {};
					if (_.settings.common.dateFormat) {
						config.dateFormat = _.settings.common.dateFormat;
					}
					$el.datepicker(config);
				});
			}
		},

		/**
		 * Make an AJAX request, adding the current filter to data.filter and then reloading the grid.
		 * (The AJAX handler on the backend should pass the request along to its load_data handler if $_POST['filter'] is present.)
		 * This means data can be sent and the grid reloaded in a single request.
		 * @param {Object} config
		 * @param {Object} config.data
		 * @param {function} config.success
		 * @param {boolean} [config.loadData = true] Whether to add the current filter to config.data.filter and make jsGrid reload based on the server response
		 * @param {boolean} [config.showLoader = true] Whether to show a loader while the request is being done. Will be ignored if config.loadData is true.
		 */
		ajax: function(config) {
			var _ = this;
			config = $.extend({
				data: {},
				loadData: true,
				showLoader: true,
				success: null,
			}, config);

			if (config.loadData) {
				var filter = _.grid.getFilter();
				filter.ajax = config;
				return _.grid.loadData(filter);
			}
			if (config.showLoader) {
				_.showLoader();
			}
			return zsc.ajax({
				data: config.data,
				error: function() {
					_.hideLoader();
					// Will also call zsc.ajaxError
				},
				success: function(data, textStatus, jqXHR) {
					if (config.showLoader) {
						_.hideLoader();
					}
					if (config.success) {
						config.success(data, textStatus, jqXHR);
					}
				},
			});
		},

		// BEGIN GRID CALLBACKS

		/**
		 * Can be overridden
		 */
		onDataLoaded: function(args) {

		},
		controllerLoadData: function(filter) {
			var _ = this;
			var data = {};

			// filter.ajax specifies custom config for the AJAX request (see also ajax() below)
			var ajax = filter.ajax || {};
			delete filter.ajax;

			data.action = _.ticker + '_load_data';

			data.filter = JSON.stringify(filter);
			data.meta = {};
			_.meta.addQueries(data.meta);

			return _.ajax({
				data: $.extend(data, ajax.data),
				success: function(data, textStatus, jqXHR) {
					_.meta.update(data.meta);
					if (ajax.success) {
						// Call custom success handler provided by filter.ajax.success
						ajax.success(data, textStatus, jqXHR);
					}
				},
				loadData: false,
				showLoader: false,
			});
		},
		controllerInsertItem: function(item) {
			return this.ajax({
				data: {
					action: this.ticker + '_insert_item',
					item: JSON.stringify(item),
				},
			});
		},
		controllerUpdateItem: function(item) {
			return this.ajax({
				data: {
					action: this.ticker + '_update_item',
					item: JSON.stringify(item),
				},
			});
		},
		controllerDeleteItem: function(item) {
			return this.ajax({
				data: {
					action: this.ticker + '_delete_item',
					id: item.id,
				},
			});
		},

		// END GRID CALLBACKS

		/**
		 * Create a thickbox.
		 * add_thickbox() must have been called in WP to use this method.
		 */
		createThickbox: function(url, txt) {
			return zsc.createThickbox(url, txt);
		},

		/**
		 * Get a jsGrid field object.
		 * @param {Object} fields Optional, defaults to the current jsGrid grid fields.
		 */
		getField: function(name, fields) {
			if (!fields) {
				fields = this.grid.fields;
			}
			for (var i in fields) {
				if (fields[i].name === name) {
					return fields[i];
				}
			}
			return null;
		},

		hideLoader: function() {
			this.$.find(".zsc-loader").hide();
		},

		onCreate: function(selector, callback) {
			return zsc.onCreate(this.$, selector, callback);
		},

		onRemove: function(selector, callback) {
			return zsc.onRemove(this.$, selector, callback);
		},

		showLoader: function() {
			var _ = this;
			// Create the loader if it doesn't exist yet
			if (_.$.find(".zsc-loader").length === 0) {
				_.$.append('<div class="zsc-loader"><div class="zsc-loader-center"></div></div>');
			}
			_.$.find(".zsc-loader").show();
		},
	}
})();
