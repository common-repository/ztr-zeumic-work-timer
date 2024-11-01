<?php
namespace Zeumic\ZSC\Core;

abstract class PluginExt extends PluginDistributable {
	/**
	 * @var PluginCore
	 */
	public $core;

	/**
	 * @param array args
	 * @param string args['core'] Ticker of the core plugin.
	 */
	protected function __construct($args) {
		parent::__construct($args);
		$this->core = Plugin::get_plugin($args['core']);

		$this->add_core_action('@init_before', array($this, 'core_init_before'));
	}

	### BEGIN CHILD HOOKS ###

	public function core_init_before() {

	}

	### END CHILD HOOKS ###

	/**
	 * Add a filter for the core plugin, so that @ in $tag is tagged using the core prefix, not the Pro prefix.
	 */
	public function add_core_filter($tag, $function_to_add, $priority = null, $accepted_args = null) {
		$tag = $this->core->tag($tag);
		return $this->add_filter($tag, $function_to_add, $priority, $accepted_args);
	}

	public function add_core_action($tag, $function_to_add, $priority = null, $accepted_args = null) {
		return $this->add_core_filter($tag, $function_to_add, $priority, $accepted_args);
	}
}
