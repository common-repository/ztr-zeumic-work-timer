<?php
namespace Zeumic\ZSC\Core;

/**
 * Requiring this file will load the entire ZSC library.
 * Should be required AFTER plugins_loaded hook is called.
 */

require 'src/util.php';
require 'src/Singleton.php';

require 'src/Ajax.php';
require 'src/Fields.php';
require 'src/Resources.php';
require 'src/Settings.php';

require 'src/Plugin.php';
require 'src/PluginDistributable.php';
require 'src/PluginCore.php';
require 'src/PluginExt.php';

require 'src/PluginLoader.php';

$loader = PluginLoader::get_instance();

global $zsc_to_load;

// Register Common
require 'src/Common.php';
$loader->register('zsc', array(
	'file' => trailingslashit(dirname($zsc_to_load['require'])) . 'zsc-zeumic-suite-common.php',
	'class' => __NAMESPACE__.'\\Common',
	'semver' => true,
));

// Allow other plugins to register themselves
do_action('zsc_register_plugins', $loader);

$loader->load_all();
