<?php
/**
 * Class for standart ajax response generation.
 */
class ajaxResponse {
	private $_result = true;
	private $_data = array();
	private $_errors = array();

	public function __construct($data = null) {
		if ($data !== null) {
			$this->success($data);
		}
	}

	/**
	 * Add error to ajax response. All errors are returned as array in 'errors' part of response.
	 *
	 * @param string $error error text
	 * @return void
	 */
	public function error($error) {
		$this->_result = false;
		$this->_errors[] = array('error' => $error);
	}

	/**
	 * Assigns data that is returned in 'data' part of ajax response.
	 * If any error was added previously, this method does nothing.
	 *
	 * @param array $data
	 * @return void
	 */
	public function success(array $data) {
		if ($this->_result) {
			$this->_data = $data;
		}
	}

	/**
	 * Output ajax response. If any error was added, 'result' is false, otherwise true.
	 *
	 * @return void
	 */
	public function send() {
		$json = new CJSON();

		if ($this->_result) {
			echo $json->encode(array('result' => true, 'data' => $this->_data));
		}
		else {
			echo $json->encode(array('result' => false, 'errors' => $this->_errors));
		}
	}
}
?>