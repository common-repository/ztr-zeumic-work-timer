<?php
/**
 * Plugin Name: ZTR Zeumic Work Timer
 * Plugin URI: http://www.zeumic.com.au
 * Description: ZTR Free [Core]; Zeumic Work Timer is a free task logging and timing plugin that can work in tandem with WooCommerce and ZWM Zeumic Work Management to help you run your business. It provides a list of tasks along with the staff member who performed them and when. Tasks can be easily added and edited.
 * Version: 1.9.7
 * Author: Zeumic
 * Author URI: http://www.zeumic.com.au
 * Text Domain: ZTR Zeumic Work Timer
 * Requires at least: 4.4
 * Tested up to: 6.2.2
 * Text Domain: zeumic
 * WC requires at least: 3.0.0
 * WC tested up to: 7
 * @package ZTR Zeumic Work Timer
 * @category
 * @author Zeumic
* */

global $zsc_dir;
$zsc_dir = __DIR__.'/zsc/';
require $zsc_dir . 'zsc-zeumic-suite-common.php';

add_filter('zsc_register_plugins', 'ztr_register_plugin');

function ztr_register_plugin($plugins) {
	$plugins->register('ztr', array(
		'file' => __FILE__,
		'require' => __DIR__.'/load.php',
		'class' => 'Zeumic\\ZTR\\Core\\Plugin',
		'semver' => 'minor',
		'deps' => array(
			'zsc' => '11.0',
			'zwm' => '?1.11',
			'wc' => '?7',
		),
	));
}
