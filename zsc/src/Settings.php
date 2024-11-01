<?php
namespace Zeumic\ZSC\Core;

/**
 * Helper class to manage settings, including registration, defaults, and settings pages.
 * Must be constructed before the admin_init hook.
 */
class Settings {
	private static $types = array(
		/** Makes a checkbox. Stores in options as "1" or "0". get_value() returns true or false. */
		'bool' => true,
		/** Makes a numerical input. get_value() returns int. */
		'int' => true,
		/** Makes a numerical input. get_value() returns float. */
		'float' => true,
		/**
		 * Makes a select input with options 'On' and 'Off'. Stores in options as "on" or "off". get_value() returns "on" or "off".
		 * @deprecated 9.0 Use 'bool' instead.
		 */
		'on_off' => true,
		/** Makes a select input with options specified by key 'options', which should be an array($option_key => $option_val). */
		'select' => true,
		/** Same as select, but get_value() returns int. */
		'select_int' => true,
		/** Same as select, but get_value() returns float. */
		'select_float' => true,
	);

	private $settings = array();
	private $pages = array(); // [$page => ['title' => ?], ...]
	private $sections = array(); // [$page => [$id => ['title' => ?, 'callback' => ?], ...], ...]
	private $heading;
	private $ticker;

	/**
	 * @param array $args
	 * @param string $args['heading']
	 * @param string $args['ticker'] Setting keys will be prefixed using this ticker.
	 */
	public function __construct($args) {
		$args = wp_parse_args($args, array(
			'heading' => '',
			'ticker' => '',
		));
		$this->heading = $args['heading'];
		$this->set_ticker($args['ticker']);

		add_action('admin_init', array($this, 'register_all'));
	}

	/**
	 * Get the actual key of a setting, as it is stored in the WP database.
	 * For example, if $this->ticker == 'zsc', the setting 'set' would be stored as 'zsc_set'.
	 * @param string $str
	 * @return string
	 */
	private function wp_key($key) {
		return pf($key, $this->ticker);
	}

	/**
	 * @param string $page Page slug
	 */
	private function tag_page($page) {
		return tag($page, $this->ticker, '-');
	}

	/**
	 * Add a setting to the current settings.
	 * @param string $key
	 * @param array $setting
	 * @param string $setting['title'] The title of the setting in settings page.
	 * @param string $setting['type'] One of self::$types. If provided, will automatically show appropriate input in settings page (e.g. checkbox for type bool), sanitize input, and ensure get_value() returns a value of an appropriate type.
	 * @param mixed $setting['default'] The default value of the setting.
	 * @param callable $setting['sanitize'] Custom sanitize callback which will override the default one for the type. Takes a single arg $input.
	 * @param bool $setting['autoload'] NOT YET IMPLEMENTED. Default: true. Whether to autoload the setting when WP starts. See WP docs.
	 * @param int $setting['min'] Can be provided if type is 'int' or 'float'. The minimum allowed value.
	 * @param int $setting['max'] Can be provided if type is 'int' or 'float'. The maximum allowed value.
	 * @param array|callable $setting['options'] Should be provided if type == 'select', and of the form [$option_key => $option_value]. Alternatively, can be a callback that returns such an array, which will be called on-demand.
	 */
	public function add_single($key, $setting) {
		if (empty($setting['page'])) {
			return;
		}
		$setting['page'] = $this->tag_page($setting['page']);
		if (empty($this->sections[$setting['page']])) {
			// Add default section to each page for settings not explicitly assigned to a section
			$this->add_section(array('id' => 'main', 'page' => $setting['page']));
		}
		$this->settings[$key] = $setting;
	}

	/**
	 * Add/merge a settings array to the current settings.
	 * @param array $settings An array of setting keys to configs (as passable to add_single()).
	 * @param string|null $default_page If provided, settings without 'page' key will be assigned to this page.
	 * @param string|null $default_section If provided, settings without 'section' key will be assigned to this section.
	 */
	public function add($settings, $default_page = null, $default_section = null) {
		foreach ($settings as $key => &$setting) {
			$setting = wp_parse_args($setting, array(
				'page' => $default_page,
				'section' => $default_section,
			));
			$this->add_single($key, $setting);
		}
	}

	/**
	 * Add a settings page. Should be called in admin_menu hook.
	 * @param array $args
	 * @param string $args['menu'] The slug of the parent menu for the settings page.
	 * @param string $args['slug'] The slug for the submenu. May contain @.
	 * @param string $args['title'] The title for the settings page (will have plugin name automatically prepended).
	 * @param string $args['menu_text'] The text/HTML to appear on the menu itself. Default: $args['title'].
	 * @param callable $args['callback'] Callback to render the entire settings page. Default: $this->output_page($args['slug']).
	 * @param callable $args['form_content'] Callback to render just the form content. Will be ignored if $args['callback'] is set.
	 */
	public function add_page($args) {
		$self = $this;

		$menu = $args['menu'];
		$slug = $this->tag_page($args['slug']);
		$title = $this->heading . ': ' . $args['title'];
		$menu_text = !empty($args['menu_text']) ? $args['menu_text'] : $args['title'];
		$callback = !empty($args['callback']) ? $args['callback'] : function() use ($self, $slug) {
			$self->output_page($slug);
		};

		$this->pages[$slug] = array('title' => $title);
		if (!empty($args['form_content']) && is_callable($args['form_content'])) {
			$this->pages[$slug]['form_content'] = $args['form_content'];
		}

		add_submenu_page($menu, $title, $menu_text, 'administrator', $slug, $callback);
	}

	public function add_section($args) {
		$id = $args['id'];
		$page = $this->tag_page($args['page']);
		$title = !empty($args['title']) ? $args['title'] : '';
		$callback = !empty($args['callback']) ? $args['callback'] : function() {};

		if (empty($this->sections[$page])) {
			$this->sections[$page] = array();
		}
		$this->sections[$page][$id] = array(
			'page' => $page,
			'title' => $title,
			'callback' => $callback,
		);
	}

	/**
	 * For now, just an alias to get_value().
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		return $this->get_value($key);
	}

	public function get_all() {
		return $this->settings;
	}

	/**
	 * Get the default value of a setting, or '' if none found.
	 * @param string $key
	 * @return mixed
	 */
	public function get_default($key) {
		if ($this->has($key) && isset($this->settings[$key]['default'])) {
			return $this->settings[$key]['default'];
		} else {
			return '';
		}
	}

	/**
	 * Get the type of a setting, or null if none found.
	 * @param string $key
	 * @return string
	 */
	public function get_type($key) {
		if (empty($this->settings[$key]) || empty($this->settings[$key]['type'])) {
			return null;
		}
		return $this->settings[$key]['type'];
	}

	/**
	 * Get the value of a setting. The same as WP's get_option(), but ensures the return value is of the correct type.
	 * Also, if the setting does not exist or is '', will return the setting's default value.
	 * @param string $key
	 * @return void
	 */
	public function get_value($key) {
		$opt = get_option($this->wp_key($key));
		if (!$this->is_set($key)) {
			return $this->get_default($key);
		}
		$type = $this->get_type($key);
		if ($type === 'int' || $type === 'select_int') {
			return intval($opt);
		} else if ($type === 'float' || $type === 'select_float') {
			return floatval($opt);
		} else if ($type === 'bool') {
			return !!$opt;
		}
		return $opt;
	}

	public function has($key) {
		return isset($this->settings[$key]);
	}

	/**
	 * Whether the given setting has been set yet. If not, its default value should be used.
	 * @param string $key
	 * @return bool
	 */
	public function is_set($key) {
		$opt = get_option($this->wp_key($key));
		return $opt !== false && $opt !== '' && !is_null($opt);
	}

	/**
	 * Not to be overridden.
	 */
	public function register_all() {
		// First register settings sections
		foreach ($this->sections as $page => $sections) {
			foreach ($sections as $id => $section) {
				add_settings_section($id, $section['title'], $section['callback'], $page);
			}
		}

		// Then register settings
		foreach ($this->settings as $key => &$setting) {
			if (!empty($setting['sanitize'])) {
				// Use a custom sanitation function if provided
				$sanitize = $setting['sanitize'];
			} else if (!empty($setting['type'])) {
				$type = $setting['type'];
				if ($this->valid_type($type)) {
					$method = array($this, "sanitize_$type");

					$sanitize = function($input) use ($method, $key) {
						return call_user_func($method, $input, $key);
					};
				}
			}

			// Actually register the setting
			if (!empty($sanitize)) {
				register_setting($setting['page'], $this->wp_key($key), $sanitize);
			} else {
				register_setting($setting['page'], $this->wp_key($key));
			}

			if (empty($setting['title'])) $setting['title'] = '';
			if (empty($setting['section'])) $setting['section'] = 'main';

			add_settings_field($this->wp_key($key), $setting['title'], array($this, 'output_field'), $setting['page'], $setting['section'], array('key' => $key));
		}
		unset($setting);
	}

	public function sanitize_bool($input, $key) {
		if ($input === '' || $input === 'default') return '';
		if (empty($input)) {
			return '0';
		} else {
			return '1';
		}
	}

	/**
	 * @param string $input
	 * @param string $key
	 * @return float|string
	 */
	public function sanitize_float($input, $key) {
		return $this->sanitize_num($input, $key, 'float');
	}

	/**
	 * @param string $input
	 * @param string $key
	 * @return int|string
	 */
	public function sanitize_int($input, $key) {
		return $this->sanitize_num($input, $key, 'int');
	}

	/**
	 * Helper function for sanitize_int and sanitize_float, since they mostly share the same logic.
	 * @param string $input
	 * @param string $key
	 * @return int|float|string
	 */
	private function sanitize_num($input, $key, $type = 'float') {
		if ($input === '') return '';
		if (!is_numeric($input)) {
			return '';
		}
		if ($type === 'int') {
			$input = intval($input);
		} else if ($type === 'float') {
			$input = floatval($input);
		}
		$setting = &$this->settings[$key];
		if (isset($setting['min']) && $input < $setting['min']) {
			return '';
		}
		if (isset($setting['max']) && $input > $setting['max']) {
			return '';
		}
		return $input;
	}

	public function sanitize_on_off($input, $key) {
		if ($input === 'on' || $input === 'off') {
			return $input;
		} else {
			return '';
		}
	}

	public function sanitize_select($input, $key) {
		$setting = &$this->settings[$key];
		if (!isset($setting['options'])) {
			$setting['options'] = array();
		}
		if (is_callable($setting['options'])) {
			$setting['options'] = call_user_func($setting['options']);
		}
		if (isset($setting['options'][$input])) {
			return $input;
		} else {
			return '';
		}
	}

	public function sanitize_select_float($input, $key) {
		$input = $this->sanitize_float($input, $key);
		return $this->sanitize_select($input, $key);
	}

	public function sanitize_select_int($input, $key) {
		$input = $this->sanitize_int($input, $key);
		return $this->sanitize_select($input, $key);
	}

	public function set_ticker($ticker) {
		$this->ticker = $ticker;
	}

	/**
	 * Output the HTML for a settings field, based on its registered callback.
	 * @param string|array $key Key of the setting whose field should be outputted. If array, should have key 'key'.
	 */
	public function output_field($key) {
		if (is_array($key)) {
			$key = $key['key'];
		}
		$setting = $this->settings[$key];
		if (!empty($setting['callback'])) {
			$callback = $setting['callback'];
		} else {
			if (!empty($setting['type']) && $this->valid_type($setting['type'])) {
				// By default, pick the default callback for the setting's type
				$callback = array($this, 'output_field_'.$setting['type']);
			} else {
				// Or just text if no type provided
				$callback = array($this, 'output_field_text');
			}
		}
		call_user_func($callback, $key);
	}

	public function output_field_bool($key) {
		output_checkbox(array(
			'id' => $this->wp_key($key),
			'name' => $this->wp_key($key),
			'value' => 1,
			'default' => $this->get_default($key),
			'checked' => $this->is_set($key) ? $this->get($key) : 'default',
		));
		/*?>
		<input
			type="checkbox"
			name=<?php echo $this->wp_key($key);?>
			id=<?php echo $this->wp_key($key);?>
			value="1"
			<?php if ($this->get_value($key) == 1) { ?>checked="checked"<?php } ?>
		/>
		<?php*/
	}

	public function output_field_float($key) {
		$this->output_field_num($key);
	}

	public function output_field_int($key) {
		$this->output_field_num($key);
	}

	public function output_field_on_off($key) {
		$this->settings[$key]['options'] = array('on' => 'On', 'off' => 'Off');
		$this->output_field_select($key);
	}

	/**
	 * Output a form input for a numeric setting.
	 * @param string $key
	 */
	private function output_field_num($key) {
		?>
		<input
			id="<?php echo $this->wp_key($key);?>"
			name="<?php echo $this->wp_key($key);?>"
			type="number"
			placeholder="<?php echo $this->get_default($key);?>"
			<?php if (!empty($this->settings[$key]['min'])) { echo 'min="'.$this->settings[$key]['min'].'"'; } ?>
			<?php if (!empty($this->settings[$key]['max'])) { echo 'max="'.$this->settings[$key]['max'].'"'; } ?>
			value="<?php echo get_option($this->wp_key($key));?>"
		/>
		<?php
	}

	public function output_field_select($key) {
		$is_set = $this->is_set($key);
		$value = $this->get_value($key);
		$default = $this->get_default($key);
		$default_label = '';

		$options = &$this->settings[$key]['options'];
		if (is_callable($options)) {
			$options = call_user_func($options);
		}

		if (isset($options[$default])) {
			$default_label = 'Default (' .$options[$default] . ')';
		}
		?>
		<select name="<?php echo $this->wp_key($key);?>" id="<?php echo $this->wp_key($key);?>">
			<option style="font-style: italic;" value="" <?php if (!$is_set) { echo 'selected="notset"'; }?>><?php echo $default_label;?></option>
			<?php foreach ($options as $opt => $label): ?>
				<option value="<?php echo $opt;?>" <?php if ($is_set && $value === $opt) { echo 'selected="selected"'; }?>><?php echo $label;?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function output_field_select_float($key) {
		$this->output_field_select($key);
	}

	public function output_field_select_int($key) {
		$this->output_field_select($key);
	}

	public function output_field_text($key) {
		?>
		<input
			id="<?php echo $this->wp_key($key);?>"
			name="<?php echo $this->wp_key($key);?>"
			type="text"
			placeholder="<?php echo $this->get_default($key);?>"
			value="<?php echo get_option($this->wp_key($key));?>"
		/>
		<?php
	}

	public function output_page($page) {
		$page = $this->tag_page($page);
		$title = $this->heading;
		if (!empty($this->pages[$page]['title'])) {
			$title = $this->pages[$page]['title'];
		}
		?>
		<div class="wrap">
			<h2><?php echo $title;?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields($page);
				if (!empty($this->pages[$page]['form_content'])) {
					$form_content = $this->pages[$page]['form_content'];
					call_user_func($form_content);
				} else {
					do_settings_sections($page);
				}
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function valid_type($type) {
		return isset(self::$types[$type]);
	}
}
