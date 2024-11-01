<?php
namespace Zeumic\ZSC\Core;

/**
 * Base class for distributable plugins (which are installed directly rather than bundled with other plugins).
 */
abstract class PluginDistributable extends Plugin {
	### BEGIN OVERRIDES

	/**
	 * Constructor.
	 * @param array $args See Plugin.
	 */
	protected function __construct($args) {
		parent::__construct($args);

		// Register plugin action links
		$this->add_filter('plugin_action_links_' . $this->pl_file(false), array($this, 'plugin_action_links'));
		$this->add_filter('plugin_row_meta', array($this, 'plugin_row_meta'));

		// Activation hook
		register_activation_hook($this->pl_file(), array($this, 'plugin_activate'), $this->pl_priority());
	}

	### END OVERRIDES

	### BEGIN CHILD HOOKS

	/**
	 * May be overridden by children, but note that it must be called before activation hooks are called (which are called very early on, before init and even plugins_loaded).
	 */
	public function plugin_activate() {
		$this->init();
		$this->plugin_install_update();
	}

	/**
	 * Add links below the plugin description on the plugins page, on the left.
	 *
	 * @param array $links Default plugin action link array.
	 * @param string $file Plugin reference.
	 * @return array New plugin action link array.
	 */
	public function plugin_action_links($links) {
		return array_merge(
			array(
				'settings' => '<a href="' . admin_url('admin.php?page=') . $this->tag('@settings', '-'). '">Settings</a>'
			),
			$links
		);
	}

	/**
	 * Add links below the plugin description on the plugins page, on the right.
	 *
	 * @param array $links Default plugin meta array.
	 * @param string $file Plugin reference.
	 * @return array New plugin meta array.
	 */
	public function plugin_row_meta($links, $file) {
		if (strpos($file, basename($this->pl_file())) !== false) {
			$links['donate'] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SLMLDPAFSUMMN" target="_blank"><span class="dashicons dashicons-heart"></span> Donate</a>';
			$links['support'] = '<a href="https://www.zeumic.com.au/contact-form/" target="_blank"><span class="dashicons dashicons-editor-help"></span> Support</a>';
			$links['other_plugins'] = '<a href="https://profiles.wordpress.org/zeumic#content-plugins" target="_blank"><span class="dashicons dashicons-star-filled"></span> Check out our other plugins</a>';
		}
		return $links;
	}

	### END CHILD HOOKS

	### BEGIN WP HOOKS



	### END WP HOOKS
}
