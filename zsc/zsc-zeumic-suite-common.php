<?php
/**
 * Library Name: ZSC Zeumic Suite Common
 * Plugin URI: http://www.zeumic.com.au
 * Description: A common library for Zeumic plugins to use.
 * Version: 11.0.2
 * Author: Zeumic
* */

// Enforce PHP >= 5.3
if (version_compare(PHP_VERSION, '5.3', '<') && !function_exists('zsc_incompatible_php_ver_notice')) {
	function zsc_incompatible_php_ver_notice() {
		?><div class="notice notice-error"><p>Zeumic plugins require PHP 5.3; <?php echo PHP_VERSION;?> found.</p></div><?php
	}
	add_action('admin_notices', 'zsc_incompatible_php_ver_notice');
	return;
}

global $zsc_to_load;
$data = get_file_data(__FILE__, array('ver' => 'Version')); // So we don't have to write out version twice
if (!isset($zsc_to_load) || version_compare($zsc_to_load['ver'], $data['ver'], '<')) {
	global $zsc_dir; // We use this global rather than __DIR__ because __DIR__ resolves symlinks, which screws up URLs

	// We want to load the latest version of ZSC
	$zsc_to_load = array('ver' => $data['ver'], 'require' => trailingslashit($zsc_dir).'load.php');
}

if (!function_exists('zsc_load')) {
	function zsc_load() {
		global $zsc_to_load;
		require $zsc_to_load['require'];
	}

	add_action('plugins_loaded', 'zsc_load');
}
