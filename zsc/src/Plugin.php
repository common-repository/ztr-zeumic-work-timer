<?php
namespace Zeumic\ZSC\Core;

/**
 * Base class for ZWM and ZTR.
 * So far, handles staff and clients (which are shared between the plugins).
 */
abstract class Plugin extends Singleton {
	/** Keep track of all active ZSC plugins. */
	private static $plugins = array();

	// Initialized by $args in constructor

	private $file;
	private $name;
	private $path; // Link to path
	private $priority;
	private $slug;
	private $ticker;
	private $ver;

	/** Props to load lazily. */
	private $lazy_props = array();

	/**
	 * Ajax request manager.
	 * @var Ajax
	 */
	public $ajax;

	/**
	 * @var Common
	 */
	public $common;

	/**
	 * Resource manager.
	 * @var Resources
	 */
	public $res;

	/**
	 * Settings manager.
	 * @var Settings
	 */
	public $settings;

	private $ok_deps;

	public static function get_plugin($ticker) {
		if (!self::has_plugin($ticker)) {
			return null;
		} else {
			return self::$plugins[$ticker];
		}
	}

	public static function has_plugin($ticker) {
		return isset(self::$plugins[$ticker]);
	}

	public function __get($name) {
		if (isset($this->lazy_props[$name])) {
			$this->$name = call_user_func($this->lazy_props[$name], $this);
		} else {
			throw new \Exception("Property $name does not exist on $this->ticker.");
		}
		return $this->$name;
	}

	### BEGIN CHILD HOOKS

	/**
	 * @param array args
	 * @param string args['file']
	 * @param string args['ticker'] The plugin's base name.
	 * @param string args['name'] The plugin's base name.
	 * @param string args['ok_deps'] Tickers of dependencies which loaded and were compatible (used by dep_ok()).
	 * @param string args['path'] Path to the plugin's root directory.
	 * @param int args['priority']
	 * @param string args['slug']
	 * @param string args['ver']
	 */
	protected function __construct($args) {
		parent::__construct($args);
		$self = $this;

		$this->file = $args['file'];
		$this->ticker = $args['ticker'];
		$this->name = $args['name'];
		$this->ok_deps = $args['deps'];
		$this->priority = $args['priority'];
		$this->path = $args['path'];
		$this->slug = $args['slug'];
		$this->ver = $args['ver'];

		self::$plugins[$this->ticker] = $this;

		add_action('init', array($this, 'init'), $this->priority);
		add_action('admin_init', array($this, 'plugin_install_update'), $this->priority);
		add_action('admin_init', array($this, 'admin_init'), $this->priority);
		add_action('admin_menu', array($this, 'admin_menu'), 9); // Check: does this have to be 9?

		$this->ajax = new Ajax(array(
			'ticker' => $this->pl_ticker(),
		));

		$this->res = new Resources(array(
			'base_url' => $this->pl_url(),
			'priority' => $this->priority,
			'ticker' => $this->pl_ticker(),
			'ver' => $this->pl_ver(),
		));

		$this->settings = new Settings(array(
			'heading' => $this->pl_name(),
			'ticker' => $this->pl_ticker(),
		));

		$this->common = Common::get_instance();

		// Set $this->fields to be loaded lazily
		$this->add_lazy_prop('fields', function() use ($self) {
			$fields = $self->init_fields();
			$fields = $self->apply_filters('@add_fields', $fields);
			$fields = $self->apply_filters('@process_fields', $fields);
			return $fields;
		});
	}

	/**
	 * Can be overridden by children.
	 */
	public function init() {
		
	}

	/**
	 * Not to be overridden.
	 */
	public function plugin_install_update() {
		if (!$this->pl_already_installed()) {
			if ($this->plugin_install()) {
				$this->pl_update_ver();
			}
		} else if (version_compare($this->pl_installed_ver(), $this->pl_ver() ,'<')) {
			if ($this->plugin_update($this->pl_installed_ver())) {
				$this->pl_update_ver();
			}
		}
	}

	/**
	 * Should be overridden by children.
	 * @return bool True on success, false on failure.
	 */
	public function plugin_install() {
		return true;
	}

	/**
	 * Should be overridden by children. Return true if successful, false on failure (or no update needed).
	 * Called only when the version number is higher than expected.
	 * @return bool True on success, false on failure.
	 */
	public function plugin_update($prev_ver) {
		return true;
	}

	/**
	 * Can be overridden by children.
	 */
	public function admin_init() {
		
	}

	/**
	 * Hook to declare admin menu. Should be overridden by children.
	 */
	public function admin_menu() {
		
	}

	/**
	 * Hook to init $this->fields. Should be overridden by children.
	 * @return ZSC_Fields
	 */
	public function init_fields() {
		return null;
	}

	### END CHILD HOOKS

	public function tag($str, $delim = '_') {
		return tag($str, $this->ticker, $delim);
	}

	/**
	 * Whether the given optional dependency of this plugin loaded and is compatible.
	 * @param string $dep Ticker of the dependency.
	 * @return bool
	 */
	public function dep_ok($dep) {
		return isset($this->ok_deps[$dep]);
	}

	/**
	 * Wrapper for WP add_filter(), with auto-tagging, priority and num arg detection.
	 * @param string $tag The tag. @ will be replaced with plugin prefix.
	 * @param string $function_to_add Same as for WP add_filter(). If a closure is passed in, it will receive an additional parameter $this at the start (for PHP 5.3 support, before auto $this binding).
	 * @param int $priority Will default to $this->pl_priority().
	 * @param int $accepted_args Not including first arg $this. Unlike core WP add_filter(), can automatically deduce this.
	 * @return bool Always true.
	 */
	public function add_filter($tag, $function_to_add, $priority = null, $accepted_args = null) {
		if (is_object($function_to_add) && method_exists($function_to_add, 'bindTo')) {
			// Here we explicitly unbind $this from the function.
			// Otherwise, we could accidentally use $this in a closure, and if testing on PHP >= 5.4, no error would be thrown.
			$function_to_add->bindTo(null);
		}
		$tag = $this->tag($tag);
		if (is_null($priority)) {
			// Default priority
			$priority = $this->pl_priority();
		}
		if (is_array($function_to_add)) {
			$func = new \ReflectionMethod($function_to_add[0], $function_to_add[1]);
		} else {
			$func = new \ReflectionFunction($function_to_add);
		}
		if (is_null($accepted_args)) {
			// Deduce number of args
			$accepted_args = $func->getNumberOfParameters();
		}

		return add_filter($tag, $function_to_add, $priority, $accepted_args);
	}

	/**
	 * Wrapper for $this->add_filter().
	 */
	public function add_action($tag, $function_to_add, $priority = null, $accepted_args = null) {
		$this->add_filter($tag, $function_to_add, $priority, $accepted_args);
	}

	/**
	 * Register a lazy prop, which will only be initialized when it is first accessed.
	 * @param string $name Name of the prop.
	 * @param callable $callback A callback which returns the value of the prop when it is accessed for the first time. The first argument is $this.
	 * @return void
	 */
	public function add_lazy_prop($name, $callback) {
		$this->lazy_props[$name] = $callback;
	}

	/**
	 * Wrapper for core WP apply_filters(). Supports @ like $this->add_filter(). Can pass in multiple args.
	 * @param string $tag
	 * @param mixed $value
	 * @param mixed $args...
	 * @return mixed
	 */
	public function apply_filters($tag, $value) {
		$args = func_get_args();
		$args[0] = $this->tag($args[0]);
		return call_user_func_array('apply_filters', $args);
	}

	/**
	 * Wrapper for core WP do_action(). Supports @ like $this->add_action(). Can pass in multiple args.
	 * @param string $tag
	 * @param mixed $args...
	 */
	public function do_action($tag) {
		$args = func_get_args();
		$args[0] = $this->tag($args[0]);
		return call_user_func_array('do_action', $args);
	}

	/**
	 * DEPRECATED, use zsc_order_add_product_meta() instead.
	 * Get an order array which can be parsed by the zsc_order custom jsGrid field.
	 */
	public function get_order_field($order_id, $product_id) {
		$order_id = intval($order_id);
		$product_id = intval($product_id);
		$order = array(
			'id' => $order_id,
		);
		$product = wc_get_product($product_id);
		if ($product) {
			$order['product_id'] = $product_id;
			$order['product_name'] = $product->get_name();
			$order['product_sku'] = $product->get_sku();
			$order['product_sku_link'] = $product->get_meta('sku_link', true);
			$order['product_url'] = get_permalink($product_id);
		}
		return $order;
	}

	/**
	 * Get the path of the main plugin file.
	 * @param bool $absolute If true, will return the entire absolute path. Otherwise, will be relative to the WP plugins directory (e.g. zwm-zeumic-work-management/zwm-zeumic-work-management.php).
	 */
	public function pl_file($absolute = true) {
		if ($absolute) {
			return $this->file;
		} else {
			return plugin_basename($this->file);
		}
	}

	/**
	 * Get the plugin's name, e.g. ZWM Zeumic Work Management.
	 * @param string $name_ext: If provided, will append this to the name (after a space).
	 */
	public function pl_name($name_ext = null) {
		$name = $this->name;
		if (!empty($name_ext)) {
			$name .= ' '.$name_ext;
		}
		return $name;
	}

	/**
	 * Get a system path from a path relative to the plugin's directory - e.g. pl_path("dir/a.txt") might return "/var/www/wp-content/plugins/zwm-zeumic-work-management/dir/a.txt".
	 */
	public function pl_path($path = '') {
		if (!$path) {
			return $this->path;
		}
		return trailingslashit($this->path) . $path;
	}

	public function pl_priority() {
		return $this->priority;
	}

	/**
	 * Get the plugin's slug, e.g. zwm-zeumic-work-management.
	 * @param string $ext If provided, will add to the end. E.g. if 'pro' will yield 'zwm-zeumic-work-management-pro'.
	 */
	public function pl_slug($ext = null) {
		$slug = $this->slug;
		if (!empty($ext)) {
			$slug .= '-'.$ext;
		}
		return $slug;
	}

	/**
	 * Get the plugin's ticker, e.g. zwm or zwm_pro.
	 * @param $ext If provided, will append an underscore and then this.
	 */
	public function pl_ticker($ext = null) {
		$ticker = $this->ticker;
		if (!empty($ext)) {
			$ticker .= '_'.$ext;
		}
		return $ticker;
	}

	/**
	 * Get the URL of one of the plugin's resources.
	 * @param $path The URL of the resource, relative to the plugin base URL.
	 */
	public function pl_url($path = '') {
		return plugins_url($path, $this->file);
	}

	/**
	 * Get the version of the plugin.
	 * @param $format
	 * @return string
	 */
	public function pl_ver($format = null) {
		return ver_format($this->ver, $format);
	}

	/**
	 * Check whether the plugin has been installed in the past (based on {ticker}_version).
	 */
	public function pl_already_installed() {
		$ver = $this->pl_installed_ver();
		return !empty($ver);
	}

	/**
	 * Get the currently installed version of the plugin.
	 */
	public function pl_installed_ver() {
		return get_option($this->tag('@version'));
	}

	/**
	 * Update the current version of the plugin stored in options, setting it to the version specified in the plugin's main file.
	 */
	public function pl_update_ver() {
		update_option($this->tag('@version'), $this->pl_ver());
	}

	/**
	 * Use the plugin's ticker to prefix a string. See ZSC\pf().
	 */
	public function pf($str = '', $delim = '_') {
		return pf($str, $this->pl_ticker(), $delim);
	}

	const SHA = '83b4becd85f3771aa051feb9ba8c9a8fc6fbba10';
}
