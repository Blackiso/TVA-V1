<?php 
	
	/**
	 * This class makes Crul request
	 */
	class HttpClient {

		private $curl;
		private $headers = array();

		function __construct() {
			$this->curl = curl_init();
		}

		public function set_headers($headers) {
			if (gettype($headers) == "string") {
				$headers = array($headers);
			}
			$this->headers =  $headers;
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);
		}

		public function set_body($data) {
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
		}

		public function set_un_ps($username, $password) {
			curl_setopt($this->curl, CURLOPT_USERPWD, "$username:$password");
		}

		public function get_request_headers() {
			curl_setopt($this->curl, CURLOPT_VERBOSE, 1);
			curl_setopt($this->curl, CURLOPT_HEADER, 1);
		}

		public function request($method, $link) {
			curl_setopt($this->curl, CURLOPT_URL, $link);
			curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_ENCODING, "");
			curl_setopt($this->curl, CURLOPT_MAXREDIRS, 10);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, 30);
			curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			// curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
			return $response = curl_exec($this->curl);
		}

	}