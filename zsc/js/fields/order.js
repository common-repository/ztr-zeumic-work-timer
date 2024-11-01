var jsgrid_field_name;

(function() {
	if (typeof jsGrid === 'undefined') {
		return;
	}
	var $ = jQuery;

	/**
	 * Add order field, used by ZWM and ZTR.
	 */
	var OrderField = function (config) {
		jsGrid.fields.text.call(this, config);
	};

	OrderField.prototype = new jsGrid.fields.text({
		css: 'ordernum_field',
		align: 'left',
		autosearch: true,
		itemTemplate: function(order, item) {
			if (!order) {
				return '';
			}
			if (typeof order === "number" || (typeof order === "object" && order.id)) {
				var order_id; var product_id; var product_url; var product_name; var product_sku; var product_sku_link;

				if (typeof order === "object") {
					order_id = order.id;
					product_id = order.product_id;
					product_url = order.product_url;
					product_name = order.item_name || order.product_name;
					product_sku = order.product_sku;
					product_sku_link = order.product_sku_link;
				} else {
					order_id = order;
				}

				product_id = product_id || item.product_id;
				product_url = product_url || zsc.productGet(product_id, 'url');
				product_name = product_name || zsc.productGet(product_id, 'name');
				product_sku = product_sku || zsc.productGet(product_id, 'sku');
				product_sku_link = product_sku_link || zsc.productGet(product_id, 'sku_link');

				var $id = '<a href="' + zsc.settings.adminUrl + 'post.php?post=' + order_id + '&action=edit" target="_blank">' + order_id + '</a>';
				var $product = '';
				var $sku = '';

				if (product_name) {
					$product = product_name;
					if (product_url) {
						$product = '<a href="' + product_url + '" target="_blank">' + $product + '</a>';
					}
				}
				if (product_sku) {
					$sku = product_sku;
					if (product_sku_link) {
						$sku = '<a href="' + product_sku_link + '" target="_blank">' + $sku + '</a>';
					}
				}
				return $('<span>' + $id + ' / ' + $product + ' / ' + $sku + '</span>');
			} else {
				if (typeof order === "string") {
					return order;
				}
				return order.text;
			}
		},
		insertTemplate: function(value) {
			return this.insertControl = $('<input type="text" rel="order" />');
		},
		insertValue: function() {
			return this.insertControl.val();
		},
		editTemplate: function(order, item) {
			if (!order) {
				return;
			}
			var _ = this;

			_._order = order;

			// Only allow editing if the item was manually inserted (and thus has order.text)
			if (typeof order === "number" || order.hasOwnProperty('id')) {
				var $result = _.itemTemplate(order, item);
				
				var $viewRelated = $('<a href="javascript:void(0)">Filter tasks from this order</a>');
				$viewRelated.on('click', function() {
					_.filterControl.val(item.order.id);
					_._grid.search();
				});
				
				$result = $result.add($("<br/><br/>")).add($viewRelated);
				return _.editControl = $result;
			}
			_.editControl = $('<input type="text" rel="order" />');
			if (typeof order === "object") {
				_.editControl.val(order.text);
			} else {
				_.editControl.val(order);
			}
			return _.editControl;
		},
		editValue: function() {
			var order = this._order;
			if (typeof order === "object") {
				if (order.hasOwnProperty('text')) {
					return {
						text: this.editControl.val(),
					};
				}
				return order;
			} else {
				return this.editControl.val();
			}
		},
	});

	jsGrid.fields[jsgrid_field_name] = OrderField;
})();