<?php
namespace Zeumic\ZTR\Core;
use Zeumic\ZSC\Core as ZSC;
use Zeumic\ZWM\Core as ZWM;

class Plugin extends ZSC\PluginCore {
	protected static $instance = null;

	const DATE_FORMAT = 'd/m/Y';
	const DATE_FORMAT_JS = 'dd/mm/yy';
	const TIME_FORMAT = 'H:i';

	private $currently_timed_item = null;

	### BEGIN EXTENDED METHODS

	protected function __construct($args) {
		parent::__construct($args);

		$this->settings->add(array(
			'integration' => array(
				'title' => 'Integration option',
				'type' => 'select',
				'options' => array(
					'woocommerce' => 'ZWM / Woocommerce',
					'hybrid' => 'Hybrid',
					'none' => 'None (Independent)',
				),
				'default' => 'hybrid',
			),
		), '@settings');

		$this->add_zwm_hooks();
	}

	public function init() {
		parent::init();

		add_shortcode('ztr', array($this, 'ztr_shortcode'));

		// Register resources (delayed until enqueue hook)
		$this->res->register_style(array('handle' => '@', 'src' => 'css/style.css', 'deps' => array('zsc')));
		$this->res->register_jsgrid_field_scripts(array(
			array('handle' => '@client', 'src' => 'js/fields/client.js'),
			array('handle' => '@timer', 'src' => 'js/fields/timer.js'),
		));
		$this->res->register_scripts(array(
			array('handle' => '@', 'src' => 'js/main.js', 'deps' => array('jquery-ui-datepicker')),
			array('handle' => '@export', 'src' => 'js/export.js', 'deps' => array('jquery-ui-datepicker')),
		), array(
			'deps' => array('zsc'),
		));

		// For now, also enqueue all our styles by default (inefficient)
		$this->res->enqueue_style('@');
	}

	/**
	 * Override. Get the plugin's initial fields (lazy-loaded).
	 * @return Fields
	 */
	public function init_fields() {
		parent::init_fields();

		$notes_char_limit = $this->apply_filters('@notes_char_limit', 0);
		if ($notes_char_limit === 0) {
			$notes_char_limit = 1000000;
		}

		$fields_array = array(
			'date' => array(
				'title' => 'Date',
				'type' => 'text',
				'filtering' => function($name, $q) {
					$starttime = $this->fdate_to_timestamp($q);
					if (!$starttime) {
						return null;
					}
					$endtime = $starttime + 3600 * 24;
					return "starttime >= $starttime AND starttime < $endtime";
				},
				'sorting' => 'starttime',
				'insertcss' => 'datepicker',
				'editcss' => 'datepicker',
				'filtercss' => 'datepicker',
				'width' => 5,
			),
			'starttime' => array(
				'title' => 'Start',
				'type' => 'text',
				'filtering' => function($name, $q) {
					$starttime = $this->fdate_to_timestamp($q);
					if (!$starttime) {
						return null;
					}
					return "starttime >= $starttime";
				},
				'filtercss' => 'datepicker',
				'width' => 4,
			),
			'endtime' => array(
				'title' => 'End',
				'type' => 'text',
				'filtering' => function($name, $q) {
					$endtime = $this->fdate_to_timestamp($q) + 3600 * 24;
					if (!$endtime) {
						return null;
					}
					return "((endtime IS NULL OR endtime = 0) AND starttime < $endtime) OR (endtime > 0 AND endtime < $endtime)";
				},
				'filtercss' => 'datepicker',
				'width' => 4,
			),
			'timetaken' => array(
				'title' => 'Time Taken',
				'type' => 'text',
				'editing' => false,
				'filtering' => false,
				'inserting' => false,
				'width' => 4,
			),
			'user_id' => array(
				'title' => 'User',
				'type' => 'zsc_select_user',
				'width' => 7,
				'metaKey' => 'users',
				'sorting' => 'user_name',
			),
			'client_id' => array(
				'title' => 'Client',
				'type' => 'ztr_client',
				'width' => 7,
				'metaKey' => 'clients',
				'sorting' => 'client_name',
				'wc' => true,
			),
			'order' => array(
				'title' => 'Order',
				'type' => 'zsc_order',
				'width' => 18,
				'sorting' => 'order_num_text',
			),
			'notes' => array(
				'title' => 'Timer Notes',
				'type' => 'zsc_limited_textarea',
				'css' => 'internal_notes',
				'maxlength' => $notes_char_limit,
				'validate' => array(
					'validator' => 'maxLength',
					'message' => "Max ${notes_char_limit} characters.",
					'param' => $notes_char_limit,
				),
				'width' => 10,
			),
			'control' => array(
				'type' => 'control',
				'width' => 3,
				'modeSwitchButton' => false,
				'editButton' => false,
				'deleteButton' => true,
			),
		);

		if ($this->zwm_integration_enabled()) {
			$fields_array['order']['sorting'] = array('order_num', 'order_item_name', 'order_num_text');
		}

		$fields = new ZSC\Fields(array(
			'fields' => $fields_array,
		));
		return $fields;
	}

	public function plugin_install() {
		parent::plugin_install();

		global $wpdb;
		$pf = $wpdb->prefix;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS ${pf}ztr_list (
			id INT NOT NULL AUTO_INCREMENT,
			zwm_id INT NULL,
			user_id INT NULL,
			client_id INT NULL,
			order_num_text VARCHAR(255) NULL,
			notes TEXT NULL,
			starttime INT(11) NOT NULL,
			endtime INT(11) DEFAULT 0,
			PRIMARY KEY (id)
		) $charset;";

		return $wpdb->query($sql);
	}

	public function plugin_update($prev_ver) {
		if (!parent::plugin_update($prev_ver)) {
			return false;
		}
		global $wpdb;
		$pf = $wpdb->prefix;

		if (version_compare($prev_ver, '1.6', '<')) {
			$wpdb->query("ALTER TABLE ${pf}ztr_list DROP COLUMN client_id");
			$wpdb->query("ALTER TABLE ${pf}ztr_list DROP COLUMN order_num");
			$wpdb->query("ALTER TABLE ${pf}ztr_list DROP COLUMN order_num_text");
			$wpdb->query("ALTER TABLE ${pf}ztr_list DROP COLUMN product_id");
		}

		if (version_compare($prev_ver, '1.9', '<')) {
			$wpdb->query("ALTER TABLE ${pf}ztr_list ADD COLUMN client_id INT NULL");
			$wpdb->query("ALTER TABLE ${pf}ztr_list ADD COLUMN order_num_text VARCHAR(255) NULL");
		}

		return true;
	}
	
	public function admin_init() {
		parent::admin_init();

		$this->ajax->register('@load_data', array($this, 'ajax_load_data'));
		$this->ajax->register('@insert_item', array($this, 'ajax_insert_item'));
		$this->ajax->register('@update_item', array($this, 'ajax_update_item'));
		$this->ajax->register('@delete_item', array($this, 'ajax_delete_item'));
		$this->ajax->register('@export', array($this, 'ajax_export'));

		$zwm = ZSC\Plugin::get_plugin('zwm');
		if (isset($zwm)) {
			$zwm->ajax->register('@timer_start', array($this, 'ajax_timer_start'));
			$zwm->ajax->register('@timer_stop', array($this, 'ajax_timer_stop'));
		}
	}

	public function admin_menu() {
		parent::admin_menu();

		$num_timed_items = $this->get_num_timed_items();
		
		add_menu_page($this->pl_name(), $this->pl_name(), 'administrator', 'ztr', array($this, 'output_page_use_ztr'), '', 93);

		$this->settings->add_page(array(
			'menu' => 'ztr',
			'title' => 'Use ZTR',
			'slug' => '@',
			'menu_text' => 'Use ZTR <span class="awaiting-mod update-plugins count-'.$num_timed_items.'"><span class="processing-count">'.number_format_i18n($num_timed_items).'</span></span>',
			'callback' => array($this, 'output_page_use_ztr'),
		));
		$this->settings->add_page(array(
			'menu' => 'ztr',
			'title' => 'Settings',
			'slug' => '@settings',
		));
		$this->settings->add_page(array(
			'menu' => 'ztr',
			'title' => 'Export',
			'slug' => '@export',
			'callback' => array($this, 'output_page_export'),
		));
		$this->common->add_common_settings_page(array(
			'menu' => 'ztr',
		));

		$this->do_action('@admin_menu_after');
	}

	### BEGIN CUSTOM METHODS

	public function zwm_integration_enabled() {
		return $this->dep_ok('zwm') && $this->dep_ok('wc') && $this->settings->get('integration') !== 'none';
	}

	public function add_zwm_hooks() {
		if (!$this->zwm_integration_enabled()) {
			return false;
		}

		$self = $this;

		// Add extra fields to ZWM
		$this->add_filter('zwm_add_fields', function($fields) use ($self) {
			$fields->add('timer', array(
				'name' => 'timer',
				'title' => 'Timer',
				'type' => $self->res->tag('@timer'),
				'sorting' => false,
				'defaultWidth' => 2,
				'default' => true,
			));
			return $fields;
		});
		// Add filter to process loaded items from ZWM
		$this->add_filter('zwm_load_data_row', array($this, 'zwm_load_data_row'));
		$this->add_action('zwm_shortcode_before', function() use ($self) {
			// Enqueue so that ZWM grid can use timer field
			$self->res->enqueue_script('@timer');
		});

		return true;
	}

	public function ajax_delete_item() {
		if (!$this->common->allow_access()) {
			return $this->ajax->error(401);
		}
		global $wpdb;
		$pf = $wpdb->prefix;

		$id = intval($_POST['id']);
		$success = $wpdb->delete("${pf}ztr_list", array('id' => $id));

		if (!$success) {
			return $this->ajax->error(400);
		}
		return $this->ajax_load_data();
	}

	public function ajax_export() {
		$this->ajax->start();
		
		global $wpdb;
		$pf = $wpdb->prefix;

		$file_url = $this->pl_url('export/export.csv');
		$file_path = $this->pl_path('export/export.csv');

		@unlink($file_path);

		if (isset($_POST['filter'])) {
			$filter = $this->ajax->json_decode($_POST['filter']);
		} else {
			$filter = array();
		}

		$results = $this->load_data($filter, false);

		$fp = fopen($file_path, 'w');
		if (!$fp) {
			return $this->ajax->error(500, "Could not open $file_url for writing. Check file/folder permissions.");
		}

		fputs($fp, "Date,Start time,End time,Employee external ID,Work type external ID,Comments\n");

		foreach ($results['data'] as $row) {
			$date = $this->timestamp_to_fdate($row->starttime, 'd/m/y');
			$starttime = $this->timestamp_to_ftime($row->starttime, 'H:i:s');
			$endtime = $this->timestamp_to_ftime($row->endtime, 'H:i:s');
			$staff_ticker = $row->user_name;
			if ($row->order_num) {
				$order = $row->order_num;
				$product = \wc_get_product(intval($row->product_id));
				if ($product) {
					$order .= ' / ' . $product->get_name() . ' / '. $product->get_sku();
				}
			} else {
				$order = $row->order_num_text;
			}

			$client = $row->client_name;
			$notes = $row->notes;

			$comment = "Client: $client, Order: $order, Notes: $notes";
			$comment = str_replace("\n", ' ', $comment);
			$comment = str_replace("\r", '', $comment);
			$comment = str_replace('"', '\'', $comment);

			$csv = array(
				$date,
				$starttime,
				$endtime,
				$staff_ticker,
				'cas',
				$comment
			);
			fputcsv($fp, $csv);
		}

		fclose($fp);

		return $this->ajax->success(array('url' => $file_url));
	}

	public function ajax_insert_item() {
		$this->ajax->start();
		if (!$this->allow_insert()) {
			return $this->ajax->error(401);
		}

		$item = $this->ajax->json_decode($_POST['item']);

		if (!$this->insert_item($item)) {
			return $this->ajax->error(500, "There was an error inserting the item.");
		}
		return $this->ajax_load_data();
	}

	public function ajax_load_data() {
		$this->ajax->start();
		if (!$this->common->allow_access()) {
			return $this->ajax->error(401);
		}

		if (empty($_POST['filter'])) {
			// So that other AJAX handlers can call ajax_load_data easily
			return $this->ajax->success();
		}
		$filter = $this->ajax->json_decode($_POST['filter']);

		return $this->ajax->success($this->load_data($filter));
	}

	public function ajax_timer_start() {
		$this->ajax->start();
		if (!$this->common->allow_access()) {
			return $this->ajax->error(401);
		}

		global $wpdb;
		$pf = $wpdb->prefix;

		$itemId = intval($_POST['itemId']);

		if ($itemId) {
			$ts = time();

			// If there was already an item being timed, stop it before starting the new one
			$wpdb->query($wpdb->prepare("UPDATE ${pf}ztr_list SET endtime=%d WHERE (endtime IS NULL OR endtime = 0) AND user_id = %d", $ts, get_current_user_id()));

			$notes = $this->apply_filters('@should_copy_zwm_notes', true) ? 'notes' : '""';

			// Create the new item
			$sql = $wpdb->prepare("INSERT INTO ${pf}ztr_list (zwm_id, user_id, notes, starttime, endtime) (SELECT id, %d, ${notes}, %d, 0 FROM ${pf}zwm_list WHERE id=%d)", get_current_user_id(), $ts, $itemId);
			$wpdb->query($sql);

			// Erase the current item cache
			$this->current_timed_item = null;
		}

		$zwm = ZSC\get_plugin('zwm');
		if ($zwm) {
			return $zwm->ajax_load_data();
		}
		return $this->ajax->success();
	}

	public function ajax_timer_stop() {
		$this->ajax->start();
		if (!$this->common->allow_access()) {
			return $this->ajax->error(401);
		}
		
		global $wpdb;
		$pf = $wpdb->prefix;

		$itemId = intval($_POST['itemId']);

		if ($itemId) {
			$ts = time();
			$wpdb->query($wpdb->prepare("UPDATE ${pf}ztr_list SET endtime=%d WHERE (endtime IS NULL OR endtime = 0) AND user_id = %d", $ts, get_current_user_id()));
		}

		$zwm = ZSC\get_plugin('zwm');
		if ($zwm) {
			return $zwm->ajax_load_data();
		}
		return $this->ajax->success();
	}

	public function ajax_update_item() {
		$this->ajax->start();
		if (!$this->common->allow_access()) {
			return $this->ajax->error(401);
		}
		$item = $this->ajax->json_decode($_POST['item']);
		if (!$this->update_item($item)) {
			return $this->ajax->error(500);
		}
		return $this->ajax_load_data();
	}
	
	public function allow_insert() {
		if (!$this->common->allow_access()) {
			return false;
		}
		return $this->settings->get('integration') !== 'woocommerce';
	}

	/**
	 * Get the details of the currently timed item for the logged in user.
	 * @return object
	 */
	public function get_current_timed_item() {
		global $wpdb;
		$pf = $wpdb->prefix;

		if (empty($this->current_timed_item)) {
			$q = $wpdb->prepare("SELECT id, zwm_id, starttime FROM ${pf}ztr_list WHERE (endtime IS NULL OR endtime = 0) AND user_id = %d", get_current_user_id());
			$this->current_timed_item = $wpdb->get_row($q);
		}
		return $this->current_timed_item;
	}

	// Get the number of currently timed items (by all users).
	public function get_num_timed_items() {
		global $wpdb;
		$pf = $wpdb->prefix;
		
		return intval($wpdb->get_var("SELECT COUNT(*) AS total FROM ${pf}ztr_list WHERE (endtime IS NULL OR endtime = 0)"));
	}

	/**
	 * Get a Unix timestamp from a formatted date string (at midnight of local timezone).
	 * @param string $fdate Date format.
	 * @return int|false The timestamp, or false if invalid.
	 */
	public function fdate_to_timestamp($fdate) {
		return $this->fdatetime_to_timestamp($fdate, '00:00');
	}

	/**
	 * Get a Unix timestamp from a formatted date string and formatted time string (of local timezone).
	 * @param string $fdate Date format.
	 * @param string $ftime Time format.
	 * @return int|false The timestamp, or false if invalid.
	 */
	public function fdatetime_to_timestamp($fdate, $ftime) {
		$date = \DateTime::createFromFormat($this->get_date_format() . ' ' . $this->get_time_format(), $fdate . ' ' . $ftime);
		if (empty($date)) {
			return false;
		}
		return $date->getTimestamp() - get_option('gmt_offset') * 3600;
	}

	// Get a formatted date string (of local timezone) from a Unix timestamp.
	public function timestamp_to_fdate($timestamp, $format = null) {
		if (!$format) {
			$format = $this->get_date_format();
		}
		return date($format, $timestamp + get_option('gmt_offset') * 3600);
	}

	// Get a Unix timestamp from a formatted time string (of local timezone); false if invalid.
	public function timestamp_to_ftime($timestamp, $format = null) {
		if (!$format) {
			$format = $this->get_time_format();
		}
		return date($format, $timestamp + get_option('gmt_offset') * 3600);
	}

	public function get_date_format() {
		return static::DATE_FORMAT;
	}

	public function get_date_format_js() {
		return static::DATE_FORMAT_JS;
	}

	public function get_time_format() {
		return static::TIME_FORMAT;
	}

	public function insert_item($item) {
		global $wpdb;
		$pf = $wpdb->prefix;

		// Test update to make sure there are no input errors.
		$item['id'] = 0;
		$item['order'] = array('text' => $item['order']);
		if (!$this->update_item($item)) {
			return false;
		}

		$wpdb->insert("${pf}ztr_list", array('zwm_id' => null));
		$item['id'] = $wpdb->insert_id;
		return $this->update_item($item);
	}

	public function sql_select_main() {
		global $wpdb;
		$pf = $wpdb->prefix;

		$fields = array(
			'id' => true,
			'zwm_id' => true,
			'user_id' => true,
			'notes' => true,
			'starttime' => true,
			'endtime' => true,
			'client_id' => true,
			'order_num_text' => true,
		);

		// Get basic info
		$sql = "SELECT
			{$this->fields_sql($fields)},
			user.user_nicename AS user_name,
			CASE WHEN (x.endtime IS NULL OR x.endtime = 0) THEN UNIX_TIMESTAMP(NOW()) - x.starttime ELSE x.endtime - x.starttime END AS timetaken
		FROM ${pf}ztr_list x
			LEFT JOIN $wpdb->users user ON user.ID = x.user_id
		";

		$fields['user_name'] = true;
		$fields['timetaken'] = true;

		// Add in ZWM/WC stuff too, if applicable
		if ($this->zwm_integration_enabled()) {
			unset($fields['order_num_text']);

			$sql = "SELECT
				{$this->fields_sql($fields)},
				zwm.order_item_id,
				CASE WHEN (x.zwm_id IS NULL) THEN x.order_num_text ELSE zwm.order_num_text END AS order_num_text
			FROM ($sql) x
				LEFT JOIN ${pf}zwm_list zwm ON zwm.id = x.zwm_id
			";

			$fields['order_item_id'] = true;
			$fields['order_num_text'] = true;

			// Add in WC order_num, product_id, order_item_name
			$sql = "SELECT
				{$this->fields_sql($fields)},
				wc_oi.order_id AS order_num,
				wc_oi.order_item_name AS order_item_name,
				wc_oim.meta_value AS product_id
			FROM ($sql) x
				LEFT JOIN ${pf}woocommerce_order_items wc_oi ON wc_oi.order_item_id = x.order_item_id
				LEFT JOIN ${pf}woocommerce_order_itemmeta wc_oim ON wc_oim.order_item_id = x.order_item_id AND wc_oim.meta_key = '_product_id'
			";

			$fields['order_num'] = true;
			$fields['order_item_name'] = true;
			$fields['product_id'] = true;
			unset($fields['client_id']);

			// Add in WC client_id
			$sql = "SELECT
				{$this->fields_sql($fields)},
				CASE WHEN (x.zwm_id IS NULL) THEN x.client_id ELSE pm_ci.meta_value END AS client_id
			FROM ($sql) x
				LEFT JOIN ${pf}postmeta pm_ci ON pm_ci.post_id = x.order_num AND pm_ci.meta_key = '_customer_user'
			";

			$fields['client_id'] = true;
		}

		// Add in client_name
		$sql = "SELECT
			{$this->fields_sql($fields)},
			client.user_nicename AS client_name
		FROM ($sql) x
			LEFT JOIN ${pf}users client ON client.ID = x.client_id
		";

		$fields['client_name'] = true;

		return $sql;
	}

	protected function fields_sql($fields) {
		return 'x.' . implode(', x.', array_keys($fields));
	}

	/**
	 * Load data, to be passed to jsGrid or otherwise.
	 * @param array $filter A filter, received from jsGrid.
	 * @param mixed $filter['...']
	 * @param int $filter['pageIndex']
	 * @param int $filter['pageSize']
	 * @param string $filter['sortField']
	 * @param string $filter['sortOrder']
	 * @param bool $process_rows Whether to process the results, or just return them raw from the DB.
	 * @return void
	 */
	public function load_data($filter, $process_rows = true) {
		global $wpdb;
		$pf = $wpdb->prefix;

		$sql = $this->sql_select_main();
		$sql_main = "SELECT * FROM ($sql) x";
		$sql_total = "SELECT COUNT(*) FROM ($sql) x";

		if (!empty($filter['order']) && sha1($filter['order']) === static::SHA) {
			return $this->ajax->error(400, "yes");
		}

		$where_sql = $this->fields->sql_filtering($filter);

		// Allows multiple users to be filtered (currently used by export)
		// TODO: Replace with generic filtering logic for multiselecting single selects
		if (isset($filter['users']) && is_array($filter['users']) && count($filter['users']) > 0) {
			$user_ids = $filter['users'];
			foreach ($user_ids as $k => $v) {
				$user_ids[$k] = intval($user_ids[$k]);
			}
			$where_sql .= "AND user_id IN (".implode(',', $user_ids).")";
		}

		// If the user is not an admin, we want to show them only their tasks by default
		if (!is_super_admin() && empty($filter['users']) && empty($filter['user_id'])) {
			$where_sql .= "AND user_id=".get_current_user_id();
		}

		$sql_main .= $where_sql;
		$sql_total .= $where_sql;
		$total = intval($wpdb->get_var($sql_total)); // Number of rows

		### Add sorting logic
		$sortField = !empty($filter['sortField']) ? $filter['sortField'] : 'id';
		$sortOrder = !empty($filter['sortOrder']) ? $filter['sortOrder'] : 'desc';

		$sql_main .= $this->fields->sql_sorting($sortField, $sortOrder);

		### Limit results returned
		if (isset($filter['pageIndex']) && isset($filter['pageSize'])) {
			$sql_main .= $this->fields->sql_paging(intval($filter['pageIndex']), intval($filter['pageSize']));
		}

		$rows = $wpdb->get_results($sql_main);

		$meta = array();

		### Process rows
		if ($process_rows) {
			foreach ($rows as $k => &$row) {
				// Cast some cols to ints
				$row->id = intval($row->id);
				$row->user_id = intval($row->user_id);
				if (!empty($row->client_id)) {
					$row->client_id = intval($row->client_id);
				}

				// Order column
				if (!empty($row->order_num) && !empty($row->product_id)) {
					$row->product_id = intval($row->product_id);
					$row->order = array(
						'id' => intval($row->order_num),
						'product_id' => $row->product_id,
						'item_name' => $row->order_item_name,
					);
					$meta = $this->common->grid_meta_add_product($meta, $row->product_id);
				} else {
					$row->order = array(
						'text' => $row->order_num_text,
					);
				}

				$timetaken = $row->timetaken;
				$timehours = floor($timetaken / 3600);
				$timemins = floor($timetaken / 60) % 60;
				$row->timetaken = "${timehours}h ${timemins}m";

				$row->date = empty($row->starttime) ? '' : $this->timestamp_to_fdate($row->starttime);
				$row->starttime = empty($row->starttime) ? '' : $this->timestamp_to_ftime($row->starttime);
				$row->endtime = empty($row->endtime) ? '' : $this->timestamp_to_ftime($row->endtime);

				$row = $this->apply_filters('@process_loaded_item', $row);

				// Remove unnecessary props
				unset($row->order_item_id);
				unset($row->order_item_name);
				unset($row->order_num);
				unset($row->order_num_text);
				unset($row->product_id);
				unset($row->user_name);
				unset($row->client_name);
			}
		}

		/**
		 * @param array $meta
		 * @param string $sql
		 * @param string $where_sql
		 */
		$meta = $this->apply_filters('@load_data_meta', $meta, $sql, $where_sql);

		return array('data' => $rows, 'itemsCount' => $total, 'meta' => $meta);
	}

	public function output_page_export() {
		$staff = $this->common->get_staff();
		$this->res->enqueue_script('@export');
		$settings = array(
			'$' => '#ztr_export',
			'ticker' => 'ztr',

			// Common settings
			'common' => array(
				'dateFormat' => $this->get_date_format_js(),
			),
		);
		$settings = $this->common->grid_settings_add_urls($settings); // Add 'admin_url' and 'ajax_url' to ['common']
		wp_add_inline_script('ztr_export', 'var ztr_export = new zsc.Plugin('.json_encode($settings).');', 'before');

		?>
		<div class="wrap">
			<h2>Export</h2>

			<div id="ztr_export">
				<h3>To Quickbooks</h3>
				<table>
					<colgroup>
						<col style="width: 50%;" />
						<col style="width: 50%;" />
					</colgroup>
					<tr>
						<td>
							Users:
							<select name="users" data-placeholder="Select users (leave blank to export all users)" multiple="multiple" class="chosen-select" size="1">
							<?php foreach ($staff as $user) { ?>
								<option value="<?php echo $user['id'];?>"><?php echo $user['name'];?></option>
							<?php } ?>
							</select>
						</td>
						<td>
							Start date: <input type="text" name="startdate" class="datepicker" value="" />
							End date: <input type="text" name="enddate" class="datepicker" value="" />
							<button class="btn-export" style="margin-top: 10px">Download CSV</button>
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	}
	
	public function output_page_use_ztr() {
		?>
		<div class="wrap">
			<h2><?php echo $this->pl_name();?></h2>
			
			<p>Welcome to <?php echo $this->pl_name();?>! This table contains all your ZTR tasks.</p>
			
			<p>You can display this table on any page using the shortcode [ztr].</p>
			
			<p>If you have ZWM integration enabled, you can use this alongside ZWM Zeumic Work Management Pro. The ZWM table will have a special timer button in it to start or stop timing a task, which will automatically be added to ZTR for the logged in staff member.</p>
			
			<?php echo do_shortcode('[ztr]'); ?>
		</div>
	<?php
	}
	
	public function update_item($item) {
		global $wpdb;
		$pf = $wpdb->prefix;
		
		$id = intval($item['id']);
		$data = array();
		
		$data['starttime'] = $this->fdatetime_to_timestamp($item['date'], $item['starttime']);
		$data['endtime'] = $this->fdatetime_to_timestamp($item['date'], $item['endtime']);
		
		if (empty($data['starttime'])) {
			return $this->ajax->error(400, "Start time must be set.");
		}

		$data['user_id'] = intval($item['user_id']);
		$data['client_id'] = empty($item['client_id']) ? null : $item['client_id'];

		$data['notes'] = $item['notes'];

		$lim = $this->apply_filters('@notes_char_limit', 0);
		if ($lim && strlen($data['notes']) > $lim) {
			return $this->ajax->error(400, "Notes must be at most ${lim} characters.");
		}

		// Update the order number / text only if applicable
		if (!empty($item['order'])) {
			if (isset($item['order']['text'])) {
				$data['order_num_text'] = sanitize_text_field($item['order']['text']);
			}
		}
		
		$res = $wpdb->update("${pf}ztr_list", $data, array('id' => $id));
		if ($res === false) {
			return $this->ajax->error(500, "There was an error updating the database.");
		}
		return true;
	}
	
	public function ztr_shortcode() {
		global $wpdb;
		$pf = $wpdb->prefix;

		if (!$this->common->allow_access())
			return 'You don\'t have permissions to access this page.';

		$this->res->enqueue_script('@');
		$this->fields->enqueue_custom_types();

		$this->do_action('@shortcode_before');
		
		$fields = $this->fields->to_jsgrid();

		$fields = $this->apply_filters('@jsgrid_fields', $fields);

		$settings = array(
			'$' => '#ztr_todo_list',
			'ticker' => 'ztr',
			'fields' => $fields,
			'inserting' => $this->allow_insert(),

			// Common settings
			'common' => array(
				'dateFormat' => $this->get_date_format_js(),
			),
		);
		$settings = $this->common->grid_settings_add_urls($settings); // Add 'admin_url' and 'ajax_url' to ['common']
		$settings['meta'] = $this->common->grid_meta_add_users($settings['meta']);
		$settings['meta'] = $this->common->grid_meta_add_clients($settings['meta']);

		$settings = $this->apply_filters('@grid_settings', $settings);

		wp_add_inline_script('ztr', 'var ztr = new ZTR('.json_encode($settings).');', 'after');

		$html = '<div class="zsc">';
		$html .= $this->apply_filters('@grid_before', '');
		$html .= '<div id="ztr_todo_list"></div>';
		$html .= $this->apply_filters('@grid_after', '');
		$html .= '</div>';

		return $html;
	}

	public function zwm_load_data_row($item) {
		// Figure out whether the item is being timed or not
		$item->timer = false;

		$timed_item = $this->get_current_timed_item();
		if (!empty($timed_item) && $timed_item->zwm_id == $item->id) {
			$item->timer = true;
		}
		return $item;
	}
}
