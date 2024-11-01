<?php
namespace Zeumic\ZSC\Core;

/**
 * Class for managing resources, settings, etc. shared by all plugins.
 */
class Common extends Plugin {
	protected static $instance = null;

	/** Whether grid_settings_add_urls() has been called. */
	private $grid_settings_added_urls = false;
	/** Whether grid_settings_add_users() has been called. */
	private $grid_meta_added_users = false;
	/** Whether grid_settings_add_clients() has been called. */
	private $grid_meta_added_clients = false;


	### BEGIN OVERRIDES ###

	public function __construct($args) {
		parent::__construct($args);

		// Profile editing handlers
		$this->add_action('show_user_profile', array($this, 'add_profile_fields'));
		$this->add_action('edit_user_profile', array($this, 'add_profile_fields'));
		$this->add_action('personal_options_update', array($this, 'save_profile_fields'));
		$this->add_action('edit_user_profile_update', array($this, 'save_profile_fields'));

		// Shared settings
		$this->settings->add(array(
			'clients_role' => array(
				'title' => 'Clients Role',
				'type' => 'select',
				'options' => function() {
					global $wp_roles;
					return $wp_roles->get_names();
				},
				'default' => '',
			),
		), '@settings');

		$this->res->register_styles(array(
			array('handle' => 'jquery-ui', 'src' => 'vendor/jquery-ui/jquery-ui.min.css'),
			array('handle' => 'jsgrid', 'src' => 'vendor/jsgrid/jsgrid.min.css'),
			array('handle' => 'jsgrid-theme', 'src' => 'vendor/jsgrid/jsgrid-theme.min.css', 'deps' => array('jsgrid')),
			array('handle' => 'select2', 'src' => 'vendor/select2/select2.min.css', 'ver' => '4.0.3'),
			array('handle' => '@', 'src' => 'css/style.css', 'deps' => array('jquery-ui', 'jsgrid', 'jsgrid-theme', 'select2')),
		));

		$this->res->register_scripts(array(
			// External libs
			array('handle' => 'jsgrid', 'src' => 'vendor/jsgrid/jsgrid.js', 'deps' => array('jquery')),
			array('handle' => 'select2', 'src' => 'vendor/select2/select2.full.min.js', 'ver' => '4.0.3', 'deps' => array('jquery')),
			array('handle' => 'jqueryui-editable', 'src' => 'vendor/x-editable/jqueryui-editable.min.js', 'deps' => array('jquery-ui-button', 'jquery-ui-tooltip')),
			array('handle' => '@checkbox', 'src' => 'js/checkbox.js'),
			array('handle' => '@util', 'src' => 'js/util.js', 'deps' => array('jsgrid', '@checkbox')),
			array('handle' => '@', 'src' => 'js/main.js', 'deps' => array('jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-sortable', 'jsgrid', 'select2', '@util', '@meta', '@meta_editor')),
		));

		$this->res->register_jsgrid_field_scripts(array(
			// Custom jsGrid field files
			array('handle' => '@bound_select', 'src' => 'js/fields/bound_select.js'),
			array('handle' => '@limited_textarea', 'src' => 'js/fields/limited_textarea.js'),
			array('handle' => '@meta', 'src' => 'js/meta.js'),
			array('handle' => '@meta_editor', 'src' => 'js/meta_editor.js'),
			array('handle' => '@multiselect', 'src' => 'js/fields/multiselect.js'),
			array('handle' => '@multiselect_user', 'src' => 'js/fields/multiselect_user.js', 'deps' => array('@multiselect')),
			array('handle' => '@order', 'src' => 'js/fields/order.js'),
			array('handle' => '@select_user', 'src' => 'js/fields/select_user.js'),
			array('handle' => '@url', 'src' => 'js/fields/url.js'),
		), array(
			'deps' => array('@util'),
		));
	}

	public function plugin_install() {
		parent::plugin_install();

		global $wpdb;

		## 9.0 update
		if (true) {
			// Before 9.0, version wasn't tracked, so this needs to also handle updates
			update_option_key('zsuite_clients_role', 'zsc_clients_role');

			$wpdb->query("UPDATE $wpdb->usermeta SET meta_key = 'zsc_inactive_client' WHERE meta_key = 'zsuite_inactive_client'");
			$wpdb->query("UPDATE $wpdb->usermeta SET meta_key = 'zsc_staff' WHERE meta_key = 'zsuite_staff'");
		}

		## Main installation
		$staff = get_user_meta(get_current_user_id(), $this->tag('@staff'), true);
		if ($staff === '') {
			// Make the user who installs the plugin a staff member, if they have not yet been explicitly marked as non-staff
			update_user_meta(get_current_user_id(), $this->tag('@staff'), 1);
		} else {
			// There was a bug in earlier version adding too much zsuite_staff
			delete_user_meta(get_current_user_id(), $this->tag('@staff'));
			update_user_meta(get_current_user_id(), $this->tag('@staff'), $staff);
		}
	}

	### END OVERRIDES ###

	/**
	 * Add a submenu for common settings to a given menu.
	 */
	public function add_common_settings_page($args) {
		$args = wp_parse_args($args, array(
			'menu' => null,
			'title' => 'Settings',
			'menu_text' => 'Zeumic Shared Settings',
		));
		$args['slug'] = '@settings';
		return $this->settings->add_page($args);
	}

	/**
	 * Not to be overridden.
	 */
	public function add_profile_fields($user) {
		if (!is_super_admin()) {
			return;
		}
		?>
		<h3>Zeumic shared settings (ZWM, ZTR)</h3>
		<table class="form-table">
			<tr>
				<th>
					<label for="<?php echo $this->tag('@staff');?>">Staff</label></th>
				<td>
					<input type="checkbox" name="<?php echo $this->tag('@staff');?>" id="<?php echo $this->tag('@staff');?>" value="1" <?php if ($this->is_staff($user->ID)) echo ' checked="checked" '; ?>  />
				</td>
			</tr>
			<tr>
				<th>
					<label for="<?php echo $this->tag('@inactive_client');?>">Inactive Client</label></th>
				<td>
					<input type="checkbox" name="<?php echo $this->tag('@inactive_client');?>" id="<?php echo $this->tag('@inactive_client');?>" value="1" <?php if ($this->is_inactive_client($user->ID)) echo ' checked="checked" '; ?>  />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Whether the current user is allowed to access the plugin at all.
	 */
	public function allow_access($user_id = 0) {
		$user_id = intval($user_id);
		if (empty($user_id))
			$user_id = get_current_user_id();
		return ($this->is_staff() || is_super_admin($user_id));
	}

	/**
	 * Get an array of active clients, sorted by name, with each element of the form array('id' => client ID, 'name' => client username).
	 */
	public function get_clients() {
		$users_arr = get_users(array('role' => $this->settings->get('clients_role')));
		$clients = array();
		foreach ($users_arr as $usr) {
			if ($this->is_inactive_client($usr->ID))
				continue;
			$clients[] = array('id' => $usr->ID, 'name' => $usr->user_nicename);
		}
		return $clients;
	}

	/**
	 * Get an array of staff, sorted by username, with each element of the form array('id' => user ID, 'name' => username).
	 */
	public function get_staff() {
		$users = get_users(array('meta_key' => $this->tag('@staff'), 'meta_value' => 1));
		$staff = array();
		foreach ($users as $usr) {
			$staff[] = array('id' => intval($usr->ID), 'name' => $usr->user_nicename);
		}
		return $staff;
	}

	/**
	 * Add common meta 'users' to a meta array, if this has not already been called by another ZSC plugin.
	 * @param array $meta The array to add to. It will be added to $meta['common']['users'].
	 * @return array The new array (also modifies original).
	 */
	public function grid_meta_add_users(&$meta) {
		if ($this->grid_meta_added_users) {
			return $meta;
		}
		if (!isset($meta['common'])) {
			$meta['common'] = array();
		}
		$meta['common']['users'] = $this->get_staff();
		$this->grid_meta_added_users = true;
		return $meta;
	}
	
	/**
	 * Add common meta 'clients' to a meta array, if this has not already been called by another ZSC plugin.
	 * @param array $meta The array to add to. It will be added to $meta['common']['clients'].
	 * @return array The new array (also modifies original).
	 */
	public function grid_meta_add_clients(&$meta) {
		if ($this->grid_meta_added_clients) {
			return $meta;
		}
		if (!isset($meta['common'])) {
			$meta['common'] = array();
		}
		$meta['common']['clients'] = $this->get_clients();
		$this->grid_meta_added_clients = true;
		return $meta;
	}

	/**
	 * Add relevant product data to $meta['common']['products'][$product_id] so that it can be used by the zsc_order jsGrid custom field.
	 * @param array $meta
	 * @param WC_Product|int $product
	 */
	public function grid_meta_add_product(&$meta, $product) {
		if (!($product instanceof WC_Product)) {
			$product = wc_get_product($product);
		}
		if (!$product) {
			return $meta;
		}
		$product_id = $product->get_id();
		if (!isset($meta['common'])) $meta['common'] = array();
		if (!isset($meta['common']['products'])) $meta['common']['products'] = array();
		if (!isset($meta['common']['products'][$product_id])) $meta['common']['products'][$product_id] = array();
		$product_meta = &$meta['common']['products'][$product_id];

		if (!isset($product_meta['name'])) {
			$product_meta['name'] = $product->get_name();
		}
		if (!isset($product_meta['sku'])) {
			$product_meta['sku'] = $product->get_sku();
		}
		if (!isset($product_meta['sku_link'])) {
			$product_meta['sku_link'] = $product->get_meta('sku_link', true);
		}
		if (!isset($product_meta['url'])) {
			$product_meta['url'] = get_permalink($product_id);
		}
		return $meta;
	}

	/**
	 * Add common settings 'adminUrl' and 'ajaxUrl' to a settings array, if this has not already been called by another ZSC plugin.
	 * @param array $settings The array to add them to. They will be added to $settings['common'].
	 * @return array The new array (also modifies original).
	 */
	public function grid_settings_add_urls(&$settings) {
		if ($this->grid_settings_added_urls) {
			return $settings;
		}
		if (!isset($settings['common'])) {
			$settings['common'] = array();
		}
		$settings['common']['adminUrl'] = admin_url('');
		$settings['common']['ajaxUrl'] = admin_url('admin-ajax.php');
		$this->grid_settings_added_urls = true;
		return $settings;
	}

	/**
	 * Whether the user is an inactive client (as set in their user profile).
	 */
	public function is_inactive_client($user_id = 0) {
		return bool_user_meta($this->tag('@inactive_client'), $user_id);
	}

	/**
	 * Whether the user is a staff member (as set in their user profile).
	 */
	public function is_staff($user_id = 0) {
		return bool_user_meta($this->tag('@staff'), $user_id);
	}

	/**
	 * Save the custom user profile fields shared by all ZSC plugins. See also: add_common_profile_fields().
	 * Not to be overridden.
	 */
	public function save_profile_fields($user_id) {
		if (!is_super_admin()) {
			return false;
		}

		update_user_meta($user_id, $this->tag('@staff'), empty($_POST[$this->tag('@staff')]) ? 0 : 1);
		update_user_meta($user_id, $this->tag('@inactive_client'), empty($_POST[$this->tag('@inactive_client')]) ? 0 : 1);
	}
}
