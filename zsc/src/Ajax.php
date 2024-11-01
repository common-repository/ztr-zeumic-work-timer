<?php
namespace Zeumic\ZSC\Core;

/**
 * Class for AJAX request manager.
 */
class Ajax {
	/**
	 * Whether an AJAX request is currently being handled.
	 * @var bool
	 */
	private $handling_request = false;

	/**
	 * The ticker to tag registered AJAX hooks with.
	 * @var string
	 */
	private $ticker;

	/**
	 * Decode JSON from one of $_POST's elements, automatically stripping slashes added by WordPress.
	 * @return array The JSON data as an array.
	 */
	public static function json_decode($data) {
		return json_decode(stripslashes($data), true);
	}

	public function __construct($args = array()) {
		$args = wp_parse_args($args, array(
			'ticker' => null,
		));
		$this->set_ticker($args['ticker']);
	}

	/**
	 * If start() has been called, respond to an AJAX request with an error, with the given status code and message, then exit the program.
	 * @return bool False, if start() has not been called.
	 */
	public function error($status = 500, $message = '') {
		if ($this->handling_request) {
			http_response_code(intval($status));
			if (!empty($message)) {
				echo $message;
			}
			exit();
		}
		return false;
	}

	/**
	 * Register a callable to be called when admin.php receives a given AJAX action
	 * The action is automatically prefixed with $this->ticker.
	 * So, e.g. register('req', 'method') will call method() when the action 'zwm_req' is received (if ZWM or ZWM Pro).
	 */
	public function register($action, $handler) {
		$action = tag($action, $this->ticker);
		add_action("wp_ajax_${action}", $handler);
		add_action("wp_ajax_nopriv_${action}", $handler);
	}

	public function set_ticker($ticker) {
		$this->ticker = $ticker;
	}

	/**
	 * Should be called at the start of any AJAX handler. This will make AJAX error and success exit and echo properly.
	 */
	public function start() {
		$this->handling_request = true;
	}

	/**
	 * If start() has been called, respond to an AJAX request with a success, outputting the given data if needed (JSON encoded if applicable), then exit the program.
	 * @return bool True, if start() has not been called.
	 */
	public function success($data = null) {
		if ($this->handling_request) {
			if (empty($data)) {
				echo 0;
			} else if (!is_numeric($data) && !is_string($data)) {
				echo json_encode($data);
			} else {
				echo $data;
			}
			exit();
		}
		return true;
	}
}