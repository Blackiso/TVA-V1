<?php
	/**
	 * This Class is Used to fetch data for paypal Api
	 */
	class Paypal extends httpClient {

		private $live_link = "https://api.paypal.com";
		private $sandbox_link = "https://api.sandbox.paypal.com";
		public  $cancel_url = "http://tva-app.test/cancel";
		public  $return_url = "http://localhost/test/file.php";
		public  $approve_url = "https://www.sandbox.paypal.com/webapps/xoonboarding?token={token}&country.x=US&locale.x=en_US#/checkout/guest";
		private $link;
		private $mode = "sandbox";
		private $client_id = "AbxeS8PkMc-yNjzVL7-0Xlv4p0oHbmL3ZQ05VuXFKXtLA8vjYyRufqhEjvwr5zcu7BaLyP5eNz8cK5Nh";
		private $client_secret = "EPNIPKaG5msoKv-9eyGsqW47vlxioBVYzWXecbMTwY3O3BxsgfMnpltDIelZ0VDWe4L_JDiAAQC-xvsx";
		private $api_token;

		function __construct() {
			parent::__construct();
			$this->link = $this->mode == "sandbox" ? $this->sandbox_link : $this->live_link;
			$this->get_token();
		}

		public function get_token() {
			$filename = "../main-files/paypal-token.dat";
			$file = fopen($filename, "r+");
			$file_data = fread($file, filesize($filename));
			$file_data = json_decode($file_data);

			$this->api_token = $file_data->token;
			if (time() < $file_data->exp) return;

			$this->set_headers(array(
				"content-type: application/json",
				"Accept-Language: en_US"
			));
			$this->set_body("grant_type=client_credentials");
			$this->set_un_ps($this->client_id, $this->client_secret);
			$data = json_decode($this->request("POST", $this->link."/v1/oauth2/token"));
			$this->api_token = $data->access_token;
			$exp = time() + 60*60*8;
			$file_write = [
				"token" => $this->api_token,
				"exp" => $exp
			];
			$file_write = json_encode($file_write);
			ftruncate($file, 0);
			rewind($file);
			fwrite($file, $file_write);
			fclose($file);
		}

		public function create_order($config) {
			$this->set_headers(array(
				"authorization: Bearer $this->api_token",
				"content-type: application/json"
			));
			$this->set_body($config);
			return $this->request("POST", $this->link."/v2/checkout/orders");
		}

		public function capture_order($order_id) {
			$this->set_headers(array(
				"authorization: Bearer $this->api_token",
				"content-type: application/json"
			));
			return $this->request("POST", $this->link."/v2/checkout/orders/$order_id/capture");
		}

		public function order_details($order_id) {
			$this->set_headers(array(
				"authorization: Bearer $this->api_token",
				"content-type: application/json"
			));
			return $this->request("GET", $this->link."/v2/checkout/orders/$order_id");
		}

		public function refund($capture_id, $body = array()) {
			$body = (object) $body;
			$this->set_headers(array(
				"authorization: Bearer $this->api_token",
				"content-type: application/json"
			));
			$this->set_body($body);
			return $this->request("POST", $this->link."/v2/payments/captures/$capture_id/refund");
		}
	}