<?php
namespace Zeumic\ZSC\Core;

/**
 * Helper class to manage jsGrid fields, including SQL generation for sorting and filtering.
 */
class Fields {
	/**
	 * @var array
	 */
	private $fields;

	/**
	 * The settings object on which to register settings, if necessary.
	 * @var Settings
	 */
	private $settings;

	/**
	 * Tracks which register_settings_*() have been called.
	 * @var array
	 */
	private $registered_settings = array();

	/**
	 * @param array $args
	 * @param array $args['fields']
	 * @param Settings $args['settings']
	 * @param string $args['ticker']
	 */
	public function __construct($args) {
		$defaults = array(
			'fields' => array(),
			'settings' => null, // Settings object to use to store enabled/width settings
		);
		$args = wp_parse_args($args, $defaults);
		$this->fields = $args['fields'];
		$this->settings = $args['settings'];
	}

	/**
	 * Add a new field.
	 * @param string $name The field's desired name.
	 * @param string $field The field config.
	 */
	public function add($name, array $field) {
		$this->fields[$name] = $field;
	}

	/**
	 * Get an array of all the non-secret field names.
	 * @return string[]
	 */
	public function all() {
		$names = array();
		foreach ($this->fields as $name => $f) {
			if (!$this->is_secret($name)) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Enqueue scripts for all custom field types present and enabled.
	 * Will also try to enqueue the Pro addition to the script by appending _pro to the handle.
	 * Uses wp_enqueue_script, so should be called at or after WP enqueue hooks.
	 */
	public function enqueue_custom_types() {
		foreach ($this->fields as $name => $field) {
			if (empty($field['type']) || !$this->is_enabled($name)) {
				continue;
			}
			if ($field['type'][0] === 'z') { // All Zeumic custom field types begin with z
				wp_enqueue_style($field['type']);
				wp_enqueue_script($field['type']); // Will silently fail if such a script has not been registered
			}
		}
	}

	/**
	 * Get the filtering callback of the given field.
	 * @param string $name Field name.
	 * @return callable|false
	 */
	private function filtering_callback($name) {
		if (!isset($this->filtering_map)) {
			$this->filtering_map = apply_filters('zsc_fields_filtering_map', array(
				'text' => 'exact',
				'textarea' => 'rough',
				'select' => 'exact',
				'zsc_bound_select' => 'select',
				'zsc_limited_textarea' => 'rough',
				'zsc_select_user' => 'id',
				'zsc_url' => 'rough',

				/** For numbers, supporting common operators. */
				'number' => function($name, $q) {
					global $wpdb;
					$ops = array(
						'>' => '>',
						'=' => '=',
						'<' => '<',
						'==' => '=',
						'<=' => '<=',
						'>=' => '>=',
						'!=' => '<>',
						'<>' => '<>',
					);
					$op = '=';
					foreach ($ops as $find => $op_sql) {
						if (substr($q, 0, strlen($find)) === $find) {
							$op = $op_sql;
							$q = substr($q, strlen($find));
							break;
						}
					}
					return $wpdb->prepare("$name $op %f", $q);
				},
				/** For numerical IDs. */
				'id' => function($name, $q) {
					$id = intval($q);
					if (!$id) {
						return null;
					}
					return "$name = $q";
				},
				/** For exact string matches. */
				'exact' => function($name, $q) {
					global $wpdb;
					return $wpdb->prepare("$name = %s", $q);
				},
				/** For rough string matches. */
				'rough' => function($name, $q) {
					global $wpdb;
					return $wpdb->prepare("$name LIKE %s", '%'.$q.'%');
				},
				'zsc_order' => function($name, $q) {
					global $wpdb;
					$wheres = array();
					$order_num = 0;
					// Try to extract order number from start of query
					$q = preg_replace_callback('/^[\\s]*([0-9]+)[\\s]*/', function($matches) use (&$order_num) {
						$order_num = intval($matches[1]);
						return '';
					}, $q);
					if ($order_num) {
						// Filter matching order numbers
						$wheres[] = "order_num = $order_num";
					}
					if ($q) {
						$pids = array(-100);

						// Find products with similar name to query
						$tmps = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_type='product' AND post_title LIKE %s", '%'.$q.'%'));
						foreach ($tmps as $tmp) {
							$pids[] = $tmp->ID;
						}
						// Find products with similar SKU to query
						$tmps = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sku' AND meta_value LIKE %s", '%'.$q.'%'));
						foreach ($tmps as $tmp) {
							$pids[] = $tmp->post_id;
						}
						$wheres[] = $wpdb->prepare('product_id IN ('.implode(',', $pids).') OR order_num_text LIKE %s', '%'.$q.'%');

						// TODO: in next major ver, remove products query and replace above with:
						//$wheres[] = $wpdb->prepare('product_id IN ('.implode(',', $pids).') OR order_num_text LIKE %s OR order_item_name LIKE %s', '%'.$q.'%');
					}
					return '('.implode(') AND (', $wheres).')';
				},
			));
		}

		$callback = false;
		if ($this->has($name, 'filtering')) {
			$callback = $this->get($name, 'filtering');
		} else if ($this->has($name, 'type')) {
			$callback = $this->get($name, 'type');
		}

		$already_checked = array(); // Prevent infinite loop
		while (!is_callable($callback)) {
			if (!is_string($callback)) {
				return false;
			}
			if (!empty($already_checked[$callback])) {
				trigger_error("Infinite loop while resolving filtering callbacks: " . implode(',', $already_checked));
				return false;
			}
			$already_checked[$callback] = true;
			if (empty($this->filtering_map[$callback])) {
				return false;
			}
			$callback = $this->filtering_map[$callback];
		}
		return $callback;
	}

	/**
	 * Get the field with the given name, or one of its attributes.
	 * @param string $name Name of the field to get.
	 * @param string $attr If specified, will get a specific attribute of the field, e.g. 'type' or 'title'.
	 * @return array|mixed|null
	 */
	public function get($name, $attr = null) {
		if (!$this->has($name)) {
			return null;
		}
		if (!$attr) {
			return $this->fields[$name];
		}
		if ($this->has($name, $attr)) {
			return $this->fields[$name][$attr];
		}
		return null;
	}

	/**
	 * Get the (raw) width of a given field.
	 * Will try each of the following and return them if they exist:
	 *     $this->get($name, 'width')
	 *     setting '{settings_ticker}_field_w_$name'
	 *     $this->get($name, 'defaultWidth')
	 *     1
	 * @param string $name
	 * @return int 0 if field doesn't exist.
	 */
	public function get_width($name) {
		if (!$this->has($name)) {
			return 0;
		}
		$width = $this->get($name, 'width');
		if ($width) {
			return $width;
		}
		if (!empty($this->settings)) {
			$width = intval($this->settings->get_value("field_w_$name"));
			if ($width) {
				return $width;
			}
		}
		$width = $this->get($name, 'defaultWidth');
		if ($width) {
			return $width;
		}
		return 1;
	}

	/**
	 * Whether there is a field with the given name.
	 * @param string $name Name of the field to check.
	 * @param string $attr If specified, will check whether the given attribute is defined for the field.
	 * @return boolean
	 */
	public function has($name, $attr = null) {
		if (!isset($this->fields[$name])) {
			return false;
		}
		if ($attr && !isset($this->fields[$name][$attr])) {
			return false;
		}
		return true;
	}

	/**
	 * Get whether the given field is enabled.
	 * @param string $name
	 * @return boolean
	 */
	public function is_enabled($name) {
		if (!$this->has($name)) {
			return false;
		}
		// Secret fields are always enabled
		if ($this->is_secret($name)) {
			return true;
		}
		if (empty($this->settings)) {
			return true;
		}
		$enabled = $this->settings->get_value("field_$name");
		return $enabled === 'on' || $enabled === true || $enabled === ''; // Enabled by default
	}

	/**
	 * Get whether the given field is secret.
	 * @param string $name
	 * @return boolean
	 */
	public function is_secret($name) {
		if (!$this->has($name)) {
			return false;
		}
		return !!$this->get($name, 'secret');
	}

	/**
	 * Get whether the given field is sortable.
	 * @param string $name
	 * @return boolean
	 */
	public function is_sortable($name) {
		if (!$this->has($name)) {
			return false;
		}
		if (!$this->has($name, 'sorting')) {
			// Sortable by default
			return true;
		}
		return $this->get($name, 'sorting');
	}

	/**
	 * Output a form for all registered field settings.
	 * Which settings are shown depends on which register_settings_*() have been called.
	 * Secret fields are not shown.
	 */
	public function output_settings_form() {
		?>
		<table class="form-table ps-form-table">
		<?php foreach ($this->fields as $name => $field) {
			if ($this->is_secret($name)) {
				continue;
			}
			?>
			<tr>
				<th scope="row"><?php echo $this->get($name, 'title'); ?></th>
				<?php if (!empty($this->registered_settings['enabled'])): ?>
				<td>
					<?php $this->settings->output_field("field_$name");?>
				</td>
				<?php endif; if (!empty($this->registered_settings['width'])): ?>
				<td>
					<label>Width</label>
					<?php $this->settings->output_field("field_w_$name");?>
				</td>
				<?php endif; ?>
			</tr>
		<?php } ?>
		</table>
		<?php
	}

	/**
	 * Register settings '@field_$name' for each non-secret field.
	 * Make sure that 'default' is set to true for the fields that should be enabled by default.
	 * @param string $page The page to put the settings on.
	 */
	public function register_settings_enabled($page) {
		if (!empty($this->registered_settings['enabled'])) {
			return;
		}
		$this->registered_settings['enabled'] = true;

		foreach ($this->fields as $name => $field) {
			if ($this->is_secret($name)) {
				continue;
			}

			// Add each field width to settings
			$this->settings->add(array(
				"field_$name" => array(
					'title' => $field['title'],
					'page' => $page,
					'type' => 'bool',
					'default' => !empty($field['default']),
				),
			));
		}
	}

	/**
	 * Register settings '@field_w_$name' for each field.
	 * Make sure that 'defaultWidth' is set for each field.
	 * @param string $page The page to put the settings on.
	 */
	public function register_settings_width($page) {
		if (!empty($this->registered_settings['width'])) {
			return;
		}
		$this->registered_settings['width'] = true;

		foreach ($this->fields as $name => $field) {
			if ($this->is_secret($name)) {
				continue;
			}

			// Add each field width to settings
			$this->settings->add(array(
				"field_w_$name" => array(
					'title' => $this->get($name, 'title'),
					'page' => $page,
					'type' => 'natural_num',
					'default' => !empty($field['defaultWidth']) ? $field['defaultWidth'] : 1,
				),
			));
		}
	}

	/**
	 * Remove a field or its attribute.
	 * @param string $name Name of the field.
	 * @param string $attr If specified, will just remove this attribute rather than the entire field.
	 * @param bool Whether anything was removed.
	 */
	public function remove($name, $attr = null) {
		if (!$this->has($name)) {
			return false;
		}
		if (!$attr) {
			unset($this->fields[$name]);
		} else {
			if (!$this->has($name, $attr)) {
				return false;
			}
			unset($this->fields[$name][$attr]);
		}
		return true;
	}

	/**
	 * Set an attribute on a field.
	 * @param string $name Name of the field to get.
	 * @param string $attr The specific attribute of the field to set, e.g. 'type' or 'title'.
	 * @param mixed $value The new value.
	 * @return bool Whether it was successful.
	 */
	public function set($name, $attr, $value) {
		if (!$this->has($name)) {
			return false;
		}
		$this->fields[$name][$attr] = $value;
		return true;
	}

	/**
	 * Get a SQL WHERE clause that filters based on a given filter array, and according to the 'search' key of each field in $this->fields.
	 * @param array $filter
	 * @return string
	 */
	public function sql_filtering(array $filter) {
		global $wpdb;
		$wheres = array();

		// For each field, automatically add an appropriate WHERE expression, depending on $field['type'] and $field['filtering']
		// if the client is searching on that field.
		foreach ($this->fields as $name => $field) {
			// If the client is searching on this field, $filter[$name] (where $name = 'id', 'description', etc.) is set by jsGrid.
			if (empty($filter[$name])) {
				continue;
			}

			$filtering_callback = $this->filtering_callback($name);
			if (!$filtering_callback) {
				// If $filtering is still false, the field cannot be filtered on
				continue;
			}

			$search = $filter[$name];
			if (empty($search)) {
				continue;
			}

			$w = call_user_func($filtering_callback, $name, $search);
			if (!empty($w)) {
				$wheres[] = $w;
			}
		}

		$sql = ' WHERE 1 ';
		if (count($wheres) > 0) {
			$sql .= "AND (" . implode(") AND (", $wheres) . ") ";
		}
		return $sql;
	}

	/**
	 * Get a SQL LIMIT clause to limit for the given page.
	 * @param int $pageIndex
	 * @param int $pageSize
	 * @return string The SQL, or empty string if invalid input.
	 */
	public function sql_paging($pageIndex, $pageSize) {
		$pageIndex = intval($pageIndex);
		$pageSize = intval($pageSize);

		if (!$pageIndex || !$pageSize) {
			return '';
		}
		return " LIMIT ".(($pageIndex - 1) * $pageSize).", ".$pageSize;
	}

	/**
	 * Get a SQL ORDER BY clause to sort by the given field, if it is valid.
	 * If $field['sorting'] is not set or is true, use the field name as the DB col.
	 * If $field['sorting'] is a string, use that string as the DB col.
	 * If $field['sorting'] is an array, use each string in the array as DB cols.
	 * If $field['sorting'] is false, refuse to sort (return empty string).
	 * 
	 * @param string $name Name of the field to sort by.
	 * @param string $order Order ('asc' or 'desc').
	 * @return string The SQL, or empty string if invalid field.
	 */
	public function sql_sorting($name, $order = 'asc') {
		if (!$this->has($name)) {
			return '';
		}
		$order = ($order == 'desc' || $order == 'DESC') ? 'desc' : 'asc'; // Sanitize sort order for SQL

		if ($this->has($name, 'sorting')) {
			$sorting = $this->get($name, 'sorting');

			if (!$sorting) {
				return '';
			} else if (is_array($sorting)) {
				return " ORDER BY " . implode(" ${order}, ", $sorting) . " ${order} ";
			} else if (is_string($sorting)) {
				return " ORDER BY ${sorting} ${order} ";
			}
		}

		return " ORDER BY $name $order ";
	}

	/**
	 * Return a processed fields array that can be converted to JSON and directly parsed by jsGrid.
	 * @return array
	 */
	public function to_jsgrid() {
		// The final fields array that will be given to jsGrid
		$fields = array();

		foreach ($this->fields as $name => $field) {
			// Only display fields that have been enabled in Settings
			if (!$this->is_enabled($name)) {
				continue;
			}

			// Add in name, which is needed for JS Grid
			$field['name'] = $name;

			// Hide secret fields
			if ($this->is_secret($name)) {
				if (empty($field['css'])) {
					$field['css'] = '';
				} else {
					$field['css'] .= ' ';
				}
				$field['css'] .= 'secret';
				$field['width'] = 0;
			} else {
				// By default, we want to allow filtering on a field if its 'search' is set, and disallow it otherwise (unless overridden for some reason).
				if (!empty($this->filtering_callback($name))) {
					$field['filtering'] = true;
				}

				// Determine the proportional width of each field
				$field['width'] = $this->get_width($name);
			}
			
			// Remove unnecessary parts, so they aren't added to the HTML
			unset($field['defaultWidth']);
			unset($field['default']);
			unset($field['search']);
			$fields[] = $field;
		}

		// Adjust width of each visible field to a percentage of the total
		$totalWidth = 0;
		foreach ($fields as $k => &$field) {
			$totalWidth += $field['width'];
		}
		foreach ($fields as $k => &$field) {
			$field['width'] = (100 * $field['width'] / $totalWidth) . '%';
		}

		return $fields;
	}
}
