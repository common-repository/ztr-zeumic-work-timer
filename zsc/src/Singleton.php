<?php
namespace Zeumic\ZSC\Core;

abstract class Singleton {
	/**
	 * The singleton instance. Each concrete subclass must make their own copy of this.
	 * @var this
	 */
	protected static $instance = null;

	/**
	 * Get the instance of the class (which implements the Singleton pattern).
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
	 * Constructor is protected so that it cannot be invoked directly from outside the class.
	 */
	protected function __construct($args) {
	}
}
