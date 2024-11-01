<?php
namespace Zeumic\ZSC\Core;

/**
 * Prefix a string with the given ticker.
 * @param string $str The string to ticker.
 * @param string $ticker The ticker.
 * @param string $delim The delimiter.
 * @return string
 */
function pf($str, $ticker, $delim = '_') {
	$ticker = str_replace('_', $delim, $ticker);
	return $ticker . $delim . $str;
}

/**
 * Replace all @ (or another character) in a given string with a ticker or ticker prefix.
 * tag('test', 'zsc') === 'test'
 * tag('@', 'zsc') === 'zsc'
 * tag('@test', 'zsc') === 'zsc_test'
 * tag('@test', 'zsc_pro', '-') === 'zsc-pro-test'
 * @param string $str
 * @param string $ticker
 * @param string $delim
 * @param string $marker Something instead of @.
 * @return void
 */
function tag($str, $ticker = '', $delim = '_', $marker = '@') {
	$ticker = str_replace('_', $delim, $ticker);
	if ($str === $marker) {
		return $ticker;
	}
	$str = str_replace($marker, $ticker . $delim, $str);
	return $str;
}

/**
 * Deeply merge an array of default values into a target array.
 * E.g. array_deep_merge(
 * 	[
 * 		'a' => [
 * 			'b' => 1,
 * 			'c' => 2,
 *		],
 *		'd' => 1,
 * 	],
 *  [
 * 		'a' => [
 * 			'b' => 3
 * 			'd' => 4,
 * 		],
 * 		'e' => 1,
 * 	], false
 * ) (of course, the first must be a variable passed by reference) will yield:
 * 	[
 * 		'a' => [
 * 			'b' => 1,
 * 			'c' => 2,
 * 			'd' => 4,
 * 		],
 * 		'd' => 1,
 * 		'e' => 1,
 * 	]
 * 
 * @param array $target Will be overwritten by reference.
 * @param array $source
 * @param bool $overwrite If true, values from the source will overwrite the target. If false, will only write keys that do not exist in the target.
 * @return array
 */
function array_deep_merge(&$target, &$source, $overwrite = false) {
	foreach ($source as $key => &$value) {
		if (!isset($target[$key])) {
			// If the target doesn't have the key, just write it from source
			$target[$key] = $value;
		} else {
			if (is_array($value) && is_array($target[$key])) {
				// If the target has the key, and they're both arrays, recursively merge
				$target[$key] = array_deep_merge($target[$key], $value, $overwrite);
			} else if ($overwrite) {
				// If the target has the key already, only overwrite it if $overwrite is true!
				$target[$key] = $value;
			}
		}
	}
	return $target;
}

/**
 * Filter an array such that it matches the structure of another array, based on its keys.
 * 
 * Examples:
 * $array = [
 *   $key1 => $val1,
 *   $key2 => $val2,
 *   $key3 => [
 *     $subkey1 => $subval1,
 *     $subkey2 => $subval2,
 *   ],
 * ]
 * 
 * array_match_structure($array, $key1) == $val1
 * array_match_structure($array, [$key2 => true, $key1 => true]) == [$key2 => $val2, $key1 => $val1]
 * array_match_structure($array, [$key2 => true, $key3 => $subkey1]) == [$key2 => $val2, $key3 => $subval1]
 * array_match_structure($array, [$key2 => true, $key3 => [$subkey1 => true]]) == [$key2 => $val2, $key3 => [$subkey1 => $subval1]]
 * 
 * @param array|mixed $array If not an array, $array will be returned regardless (unless $filter false or null).
 * @param array|string|bool|null $filter If false or null, null will be returned.
 * @return array|mixed
 */
function array_match_structure($array, $filter = true) {
	if ($filter === false || is_null($filter)) {
		return null;
	}
	if ($filter === true) {
		return $array;
	}
	if (!is_array($array)) {
		return $array;
	}
	if (is_array($filter)) {
		$output = array();
		foreach ($filter as $key => $sub_filter) {
			if (isset($array[$key])) {
				$output[$key] = array_match_structure($array[$key], $sub_filter);
			} else {
				$output[$key] = null;
			}
		}
		return $output;
	} else {
		if (isset($array[$filter])) {
			return $array[$filter];
		} else {
			return null;
		}
	}
}

/**
 * Whether a user meta value is not empty or 0.
 */
function bool_user_meta($meta_key, $user_id = 0) {
	$user_id = intval($user_id);
	if (empty($user_id))
		$user_id = get_current_user_id();
	$meta = get_user_meta($user_id, $meta_key, true);
	return $meta && $meta !== '0';
}

/**
 * Trigger an error, print_r-ing a variable. Will log it to debug.log.
 */
function debug($var, $desc = 'Debugging') {
	trigger_error(print_r($var, true));
}

/**
 * Read the version from a file header.
 * @param string $file
 * @return string|null
 */
function file_read_ver($file) {
	$data = get_file_data($file, array('ver' => 'Version'), 'plugin');
	return $data['ver'];
}

/**
 * See PluginLoader::get_plugin().
 */
function get_plugin($ticker) {
	$loader = PluginLoader::get_instance();
	return $loader->get_plugin($ticker);
}

/**
 * Output a special checkbox.
 * @param array $args
 * @param string $args['id']
 * @param string $args['name']
 * @param string $args['value']
 * @param bool $args['default'] If provided, will make a three-state checkbox, with this as the "default" state checked value.
 * @param bool|string $args['initial'] Should be true, false or "default".
 * @return void
 */
function output_checkbox($args) {
	$args = wp_parse_args($args, array(
		'id' => null,
		'name' => null,
		'value' => '1',
		'checked' => 'default',
		'default' => null,
	));
	?>
	<input
		type="checkbox"
		<?php if ($args['id']) { echo 'id="'.$args['id'].'"'; } ?>
		<?php if ($args['name']) { echo 'name="'.$args['name'].'"'; } ?>
		value="<?php echo $args['value'];?>"
		<?php if ($args['checked'] === true) { ?> checked="checked" <?php } ?>
	<?php

	if (!is_null($args['default'])) {
		// Output a tri-checkbox
		$common = Common::get_instance();
		$common->res->enqueue_script('@checkbox');

		?>
			data-default="<?php echo $args['default'] ? 'true' : 'false';?>"
			data-checked="<?php echo $args['checked'] === 'default' ? 'default' : ($args['checked'] ? 'true' : 'false');?>"
		<?php
	}
	?>/><?php
}

/**
 * Whether a given plugin has been loaded successfully.
 * @param string $ticker
 * @return bool
 */
function plugin_loaded($ticker) {
	$loader = PluginLoader::get_instance();
	return $loader->loaded($ticker);
}

/**
 * Update the key of a WP option.
 * @param string $old_key
 * @param string $new_key
 * @return bool Whether there was a change.
 */
function update_option_key($old_key, $new_key) {
	$current_new_val = get_option($new_key); // If the new key already has a value, don't overwrite it!
	$old_value = get_option($old_key);
	if (!$current_new_val && $old_value) {
		update_option($new_key, $old_value);
	}
	delete_option($old_key);
}

/**
 * Format a standard version string.
 * @param string $ver
 * @param string $format E.g. "M.m.p"
 * @return string
 */
function ver_format($ver, $format = null) {
	if (!$format) {
		return $ver;
	}

	$output = str_replace('M', ver_part($ver, 'M'), $format);
	$output = str_replace('m', ver_part($ver, 'm'), $output);
	$output = str_replace('p', ver_part($ver, 'p'), $output);
	return $output;
}

/**
 * Get part of a ver.
 * @param string $ver M/major, m/minor, p/patch
 * @return int The part, or null if absent.
 */
function ver_part($ver, $part) {
	$parts = explode('.', $ver);

	if ($part === 'M' || $part === 'major') {
		return isset($parts[0]) ? intval($parts[0]) : null;
	} else if ($part === 'm' || $part === 'minor') {
		return isset($parts[1]) ? intval($parts[1]) : null;
	} else if ($part === 'p' || $part === 'patch') {
		return isset($parts[2]) ? intval($parts[2]) : null;
	}
	return null;
}
