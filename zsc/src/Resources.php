<?php
namespace Zeumic\ZSC\Core;

/**
 * Class for resource manager. Manages registering and enqueueing styles/scripts.
 */
class Resources {
	private static $types = array('script', 'style', 'jsgrid_field');

	/**
	 * Whether hook_enqueue_all() has been called.
	 * @var boolean
	 */
	private $already_queued_all = false;

	/**
	 * Whether the enqueue hooks have passed.
	 * @var bool
	 */
	private $hooks_finished = false;

	private $registered = array();
	private $queue = array();

	/** Scripts to register. */
	private $scripts = array();
	/** Styles to register. */
	private $styles = array();
	/** Scripts to enqueue. */
	private $scripts_to_enqueue = array();
	/** Styles to enqueue. */
	private $styles_to_enqueue = array();

	private $base_url;
	private $ticker;
	private $ver;

	/**
	 * @param array $args
	 * @param string $args['ticker'] Any @ in script/style handles will be replaced with a prefix from this ticker.
	 * @param string $args['ver'] The default version string to append to each resource's URL for cache busting.
	 */
	public function __construct($args) {
		$self = $this;

		global $wp_version;
		$args = wp_parse_args($args, array(
			'base_url' => '',
			'ticker' => '',
			'priority' => 10,
			'ver' => $wp_version,
		));
		$this->base_url = trailingslashit($args['base_url']);
		$this->ticker = $args['ticker'];
		$this->ver = $args['ver'];

		foreach (self::$types as $type) {
			$this->registered[$type] = array();
			$this->queue[$type] = array();
		}

		### Prepare script/style enqueuement logic
		add_action('wp_enqueue_scripts', array($this, 'hook_register_all_resources'), $args['priority']);
		add_action('admin_enqueue_scripts', array($this, 'hook_register_all_resources'), $args['priority']);
		add_action('wp_enqueue_scripts', array($this, 'hook_enqueue_all_resources'), $args['priority']);
		add_action('admin_enqueue_scripts', array($this, 'hook_enqueue_all_resources'), $args['priority']);
		add_action('wp_enqueue_scripts', function() use ($self) {
			$self->hooks_finished = true;
		}, 1000);
		add_action('admin_enqueue_scripts', function() use ($self) {
			$self->hooks_finished = true;
		}, 1000);
	}

	public function tag($str) {
		return tag($str, $this->ticker);
	}

	/**
	 * Enqueue all resources enqueued with enqueue_*(), at the correct hook.
	 */
	public function hook_enqueue_all_resources() {
		foreach ($this->queue as $type => $handles) {
			foreach ($handles as $handle) {
				$this->enqueue_final($type, $handle);
			}
		}
		$this->already_queued_all = true;
	}

	/**
	 * Called in WP enqueue hooks. Register all resources that have been registered through register_*().
	 * Not to be overridden.
	 */
	public function hook_register_all_resources() {
		foreach ($this->registered as $type => &$resources) {
			foreach ($resources as &$r) {
				$handle = $this->tag($r['handle']);

				$ver = !empty($r['ver']) ? $r['ver'] : $this->ver;
				$deps = !empty($r['deps']) ? $r['deps'] : array();
				foreach ($deps as &$dep) {
					$dep = $this->tag($dep);
				}

				if ($type === 'style' || $type === 'jsgrid_field_style') {
					$media = !empty($r['media']) ? $r['media'] : 'all';
					wp_register_style($handle, $this->base_url . $r['src'], $deps, $ver, $media);
				} else {
					$in_footer = isset($r['in_footer']) ? $r['in_footer'] : true; // WP puts in head by default, we want in footer by default
					wp_register_script($handle, $this->base_url . $r['src'], $deps, $ver, $in_footer);
				}

				if ($type === 'jsgrid_field_script') {
					// So that the custom field script can register the new field under the correct key.
					wp_add_inline_script($handle, "var jsgrid_field_name = '$handle';", 'before');
				}
			}
		}
	}

	/**
	 * Actually enqueues the given resource with wp_enqueue_style/script, rather than just queueing it to be enqueued.
	 */
	private function enqueue_final($type, $handle) {
		if ($type === 'style' || $type === 'jsgrid_field_style') {
			wp_enqueue_style($this->tag($handle));
		} else {
			wp_enqueue_script($this->tag($handle));
		}
		return true;
	}

	private function enqueue($type, $handle) {
		if ($this->hooks_finished && $type !== 'script' && $type !== 'jsgrid_field_script') {
			$this->debug("Trying to enqueue $type $handle too late, after the wp_enqueue_scripts/admin_enqueue_scripts hook.");
			return false;
		}
		if ($this->already_queued_all) {
			return $this->enqueue_final($type, $handle);
		}
		$this->queue[$type][] = $handle;
		return true;
	}

	/**
	 * Can be called before or after WP enqueue hooks.
	 */
	public function enqueue_jsgrid_field_script($handle) {
		return $this->enqueue('jsgrid_field_script', $handle);
	}

	/**
	 * Can be called before or after WP enqueue hooks.
	 */
	public function enqueue_jsgrid_field_style($handle) {
		return $this->enqueue('jsgrid_field_style', $handle);
	}

	/**
	 * Can be called before or after WP enqueue hooks.
	 */
	public function enqueue_script($handle) {
		return $this->enqueue('script', $handle);
	}

	/**
	 * Must be called before WP enqueue hooks.
	 */
	public function enqueue_style($handle) {
		return $this->enqueue('style', $handle);
	}

	/**
	 * @param string $type Type of resource to register.
	 */
	public function register($type, $resource) {
		if (isset($this->registered)) {
			if ($type === 'jsgrid_field_script' || $type === 'jsgrid_field_style') {
				// All jsgrid field styles/scripts depend on jsgrid, naturally
				if (!isset($resource['deps'])) {
					$resource['deps'] = array();
				}
				$resource['deps'][] = 'jsgrid';
			}
			$this->registered[$type][$resource['handle']] = $resource;
		} else {
			debug("Trying to register $type $handle too late, after the wp_enqueue_scripts hook.");
		}
	}

	public function register_multiple($type, $resources, $shared = array()) {
		foreach ($resources as &$res) {
			if (isset($res['deps']) && isset($shared['deps'])) {
				// Anything in $shared['deps'] will be added to the provided deps rather than replacing them
				$res['deps'] = array_merge($res['deps'], $shared['deps']);
			}
			$res = wp_parse_args($res, $shared);
			$this->register($type, $res);
		}
	}

	public function register_jsgrid_field_script($field) {
		$this->register('jsgrid_field_script', $field);
	}

	public function register_jsgrid_field_scripts($fields) {
		$this->register_multiple('jsgrid_field_script', $fields);
	}

	public function register_jsgrid_field_style($field) {
		$this->register('jsgrid_field_style', $field);
	}

	public function register_jsgrid_field_styles($fields) {
		$this->register_multiple('jsgrid_field_style', $fields);
	}

	public function register_script($script) {
		$this->register('script', $script);
	}

	public function register_scripts($scripts, $shared = array()) {
		$this->register_multiple('script', $scripts, $shared);
	}

	public function register_style($style) {
		$this->register('style', $style);
	}

	public function register_styles($styles, $shared = array()) {
		$this->register_multiple('style', $styles, $shared);
	}
}