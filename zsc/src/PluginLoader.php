<?php
namespace Zeumic\ZSC\Core;

class PluginLoader extends Singleton {
	protected static $instance = null;

	// Known dep names
	private static $dep_names = array(
		'php' => 'PHP',
		'wc' => 'WooCommerce',
		'zwm' => 'ZWM Zeumic Work Management',
		'zwm_pro' => 'ZWM Zeumic Work Management Pro',
		'ztr' => 'ZTR Zeumic Work Timer',
		'ztr_pro' => 'ZTR Zeumic Work Timer Pro',
		'zpr' => 'ZPR Zeumic Products Database',
		'zpr_pro' => 'ZPR Zeumic Products Database Pro',
	);

	private $plugins = array();

	private $load_result = array();

	/**
	 * Gets the instance of the class (which implements the Singleton pattern).
	 * @return this|false False if already getting instance.
	 */
	public static function get_instance($args = array()) {
		if (!isset(static::$instance)) {
			static::$instance = false; // Prevent infinite recursion
			static::$instance = new static($args);
		}
		return static::$instance;
	}

	/**
	 * Get the main instance of an instantiated plugin.
	 * @param string $ticker
	 * @return Plugin|bool|null The Plugin if loaded and instantiated successfully, true if loaded but no instance, false if failed to load, null if not registered.
	 */
	public function get_plugin($ticker) {
		if (!isset($this->load_result[$ticker])) {
			return null;
		}
		return $this->load_result[$ticker];
	}

	/**
	 * Load a plugin, first loading all its dependencies.
	 * Output an error message if it cannot be loaded.
	 * @return bool
	 */
	public function load($ticker) {
		$plugin = &$this->plugins[$ticker];

		if ($this->loaded($ticker)) {
			return true;
		}
		if ($this->load_failed($ticker)) {
			return false;
		}

		$plugin['ok_deps'] = array();

		// First load any dependencies
		foreach ($plugin['deps'] as $dep => $req_ver) {
			$result = $this->load_dep($ticker, $dep, $req_ver);

			if (!$result['success']) {
				$this->load_result[$ticker] = false;
				return false;
			}

			// With the ? modifier, the plugin can still load if its dep doesn't, but we have to make a note of that.
			if ($result['status'] !== 'success') {
				unset($plugin['deps'][$dep]);
			}

			// Update priority so that it is greater than all its dependencies.
			if ($this->is_registered($dep)) {
				$plugin['priority'] = max($plugin['priority'], $this->plugins[$dep]['priority'] + 1);
			}
		}

		if (isset($plugin['require'])) {
			// Require any required files
			if (!is_array($plugin['require'])) {
				$plugin['require'] = array($plugin['require']);
			}
			foreach ($plugin['require'] as $require) {
				require $require;
			}
		}

		$instance = true;
		if (isset($plugin['class'])) {
			// Instantiate the plugin's main class
			$instance = call_user_func(array($plugin['class'], 'get_instance'), $plugin);
		}

		$this->load_result[$ticker] = $instance;
		return true;
	}

	public function load_all() {
		foreach ($this->plugins as $ticker => &$plugin) {
			$this->load($ticker);
		}
	}

	/**
	 * Register a plugin to load.
	 * @param string $ticker Unique ticker for the plugin. If it has already been registered, the most recent version will be ultimately loaded.
	 * @return bool
	 */
	public function register($ticker, $plugin) {
		// Automatically fill in missing plugin data
		if (!is_array($plugin)) {
			$plugin = array();
		}

		// This is required
		if (empty($plugin['file'])) {
			return false;
		}
		$file_data = get_file_data($plugin['file'], array('ver' => 'Version', 'name' => 'Plugin Name'));

		$plugin['ticker'] = $ticker;

		if (empty($plugin['path'])) {
			$plugin['path'] = dirname($plugin['file']);
		}
		if (empty($plugin['ver'])) {
			$plugin['ver'] = $file_data['ver'];
		}
		if (empty($plugin['name'])) {
			$plugin['name'] = $file_data['name'];
		}
		if (empty($plugin['slug'])) {
			$plugin['slug'] = basename($plugin['file'], '.php');
		}
		if (empty($plugin['deps'])) {
			$plugin['deps'] = array();
		}
		if (empty($plugin['priority'])) {
			$plugin['priority'] = 11;
		}

		if ($this->is_registered($ticker)) {
			// If it has already been registered, only use the latest
			if (version_compare($plugin['ver'], $this->plugins[$ticker]['ver'], '<')) {
				return false;
			}
		}

		$this->plugins[$ticker] = $plugin;

		return true;
	}

	private function is_registered($ticker) {
		return isset($this->plugins[$ticker]);
	}

	/**
	 * Check whether a given plugin is compatible with its dependency.
	 * If so, load the dependency. Otherwise, output an error message.
	 * @param string $ticker Ticker of dependent.
	 * @param string $dep Ticker of dependency.
	 * @return array ['status' => string, 'success' => bool]
	 */
	private function load_dep($ticker, $dep, $req_ver) {
		$plugin = &$this->plugins[$ticker];

		// The first character of $req_ver may be a modifier. Currently supported:
		// ? Indicates a version-only dependency; the dependency need not be installed or activated, but if it is, it must have a compatible version.
		$modifier = null;
		if (!ctype_alnum($req_ver[0])) {
			$modifier = $req_ver[0];
			$req_ver = substr($req_ver, 1);
		}

		$fail = false; // The type of failure
		$status = 'success';

		## First check whether their versions are compatible
		$ver = null;
		$semver = false;

		if ($dep === 'php') {
			$ver = PHP_VERSION;
			$semver = true;
		} else if ($dep === 'wc') {
			global $woocommerce;
			if (isset($woocommerce)) {
				$ver = $woocommerce->version;
				if (version_compare($ver, '3.0', '<')) {
					$semver = 'minor';
				} else {
					$semver = true;
				}
			} else {
				$fail = true;
				$status = 'missing';
			}
		} else if ($this->is_registered($dep)) {
			$ver = $this->plugins[$dep]['ver'];
			$semver = !empty($this->plugins[$dep]['semver']) ? $this->plugins[$dep]['semver'] : false;
		} else {
			$fail = true;
			$status = 'missing';
		}

		if (!$fail && $status !== 'missing') {
			if ($req_ver === 'sync') {
				// Use the plugin's major/minor
				$req_ver = ver_format($plugin['ver'], 'M.m');
			}

			if ($this->versions_compatible($ver, $req_ver, $semver)) {
				// If the dependency is a registered plugin, it must be loaded before we can determine whether it succeeds.
				if ($this->is_registered($dep)) {
					if (!$this->load($dep)) {
						$status = 'load_failed';
						$fail = true;
					}
				}
			} else {
				$status = 'incompatible';
				$fail = true;
			}
		}

		if ($modifier === '?') {
			// With the ? modifier, it's okay if the dep fails to load.
			$fail = false;
		}

		$name = $dep;
		if ($this->is_registered($dep)) {
			$name = $this->plugins[$dep]['name'];
		}
		if (isset(self::$dep_names[$dep])) {
			$name = self::$dep_names[$dep];
		}
		$msg = $plugin['name'] . " requires $name";
		if ($status === 'load_failed') {
			$msg .= ", which failed to load.";
		} else if ($status === 'missing') {
			$msg .= ", which is either not installed or deactivated.";
		} else if ($status === 'incompatible') {
			$msg .= " $req_ver (active version: $ver).";
		}

		if ($fail) {
			add_action('admin_notices', function() use ($msg) {
				?><div class="notice notice-error"><p><?php echo $msg;?></p></div><?php
			});
		} else {
			if ($modifier === '?') {
				if ($status === 'incompatible') {
					$msg .= " You can still use them, but they will not be integrated.";

					// If the dep is there but incompatible, just output a warning (not an error, as the plugin can still load).
					add_action('admin_notices', function() use ($msg) {
						?><div class="notice notice-warning"><p><?php echo $msg;?></p></div><?php
					});
				}
			}
		}

		return array(
			'status' => $status,
			'success' => !$fail,
		);
	}

	/**
	 * Whether a plugin has been loaded successfully.
	 * @param string $ticker
	 * @return bool
	 */
	public function loaded($ticker) {
		return isset($this->load_result[$ticker]) && $this->load_result[$ticker];
	}

	/**
	 * Whether a plugin has failed to load (false if hasn't tried to load yet).
	 * @param string $ticker
	 * @return bool
	 */
	public function load_failed($ticker) {
		return isset($this->load_result[$ticker]) && !$this->load_result[$ticker];
	}

	/**
	 * @param string $ver
	 * @param string $req_ver
	 * @param string|boolean $semver true, false or 'minor'.
	 * @return bool
	 */
	private function versions_compatible($ver, $req_ver, $semver = false) {
		if (!$ver) {
			return false;
		}
		if (version_compare($req_ver, $ver, '>')) {
			return true;
		}
		// Check semver
		// In true semantic versioning (semver.org), backwards compatibility is broken only when the major is incremented.
		// In minor semantic versioning, used by ZWM/ZTR/ZPR, backwards compatibility is broken only when the major or minor is incremented.

		// Major version must always match
		if (ver_part($req_ver, 'M') !== ver_part($ver, 'M')) {
			return false;
		}
		if ($semver === 'minor' || $semver === false) {
			if (!is_null(ver_part($req_ver, 'm')) && ver_part($req_ver, 'm') !== ver_part($ver, 'm')) {
				// req['ver'] == '3' and $ver == '3.1' will pass
				// but req['ver'] == '3.0' and $ver == '3.1' will not
				return false;
			}
		}
		if ($semver === false) {
			// If no semver, we must check patches match (if patch in req['ver'])
			if (!is_null(ver_part($req_ver, 'p')) && ver_part($req_ver, 'p') !== ver_part($ver, 'p')) {
				return false;
			}
		}

		return true;
	}
}