(function() {
	var $ = jQuery;

	/**
	 * @param {zsc.Meta} [common]
	 */
	zsc.Meta = function(common) {
		this._meta = {};
		this.common = common;
	};

	zsc.Meta.prototype = {
		/**
		 * Add queries, for all meta which have had register() called, to a given object (should be a subobject of a jQuery AJAX data request object).
		 * @param {{}} obj
		 */
		addQueries: function(obj) {
			if (this.common) {
				if (!obj.common) {
					obj.common = {};
				}
				// First add common
				this.common.addQueries(obj.common);
			}

			// Then add specific ones
			for (var key in this._meta) {
				if (this._meta[key].updateQuery) {
					if (!obj[key]) {
						obj[key] = null;
					}
					obj[key] = this._meta[key].updateQuery(this.get(key));
				}
			}
		},

		/**
		 * Return a meta value.
		 * @param {string} key
		 */
		get: function(key) {
			if (!this.has(key)) {
				return undefined;
			}
			return this._meta[key].value;
		},

		has: function(key) {
			return this._meta.hasOwnProperty(key);
		},

		/**
		 * Call a callback whenever the given meta is updated.
		 * @param {string} key
		 * @param {function} callback (new_value) => void
		 * @param {boolean} [doImmediately = false] If true, will also execute the callback immediately.
		 */
		onUpdate: function(key, callback, doImmediately) {
			if (!this.has(key)) {
				this._meta[key] = {};
			}
			if (!this._meta[key].onUpdate) {
				this._meta[key].onUpdate = [];
			}
			this._meta[key].onUpdate.push(callback);
			if (doImmediately) {
				callback(this.get(key));
			}
		},

		/**
		 * Register some meta to poll the server.
		 * @param {Object} meta
		 * @param {string} meta[key] Key of a meta to register.
		 * @param {any} meta[key].value The initial value of the meta.
		 * @param {function} meta[key].updateQuery This function will be called on the current value and sent to the server in data.meta[key], and the server will return the updated value if it has changed.
		 */
		register: function(meta) {
			for (var key in meta) {
				var config = meta[key];
				if (!this.has(key)) {
					this._meta[key] = {};
				}
				if (typeof config.updateQuery === 'function') {
					this._meta[key].updateQuery = config.updateQuery;
				}
				if (config.value !== undefined) {
					this.set(key, config.value);
				}
			}
		},

		/**
		 * Set the value of a ZSC global key/value meta pair.
		 * @param {string} key
		 * @param {string} value
		 */
		set: function(key, value) {
			if (key === 'common' && this.common) {
				this.common.update(value);
			}
			if (!this.has(key)) {
				this._meta[key] = {};
			}
			this._meta[key].value = value;

			// Call all callbacks
			if (this._meta[key].onUpdate) {
				for (var i = 0; i < this._meta[key].onUpdate.length; i++) {
					this._meta[key].onUpdate[i](value);
				}
			}
		},

		update: function(meta) {
			for (var key in meta) {
				this.set(key, meta[key]);
			}
		},
	};
})();
