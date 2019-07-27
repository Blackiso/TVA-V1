<?php 
	
	abstract class JWT {

		private $algo = 'sha384';
		private $type = 'JWT';
		public  $jwt_type = 'Bearer';

		public function sign_JWT($payload, $secret) {
			$payload->exp = strtotime("+1 week");
			$payload->rnw = strtotime("+30 minutes");

			$header = (object) array();
			$header->alg = $this->algo;
			$header->typ = $this->type;

			$data = $this->encode($header).".".$this->encode($payload);
			$jwt_signiture = hash_hmac($this->algo, $data, $secret);
			$jwt = $data.".".$jwt_signiture;
			return $jwt;
		}

		public function verify_JWT($old_key, $secret) {
			$old_key = explode('.', $old_key);
			$header = $this->decode($old_key[0]);
			$signiture = $old_key[2];

			$data = $old_key[0].".".$old_key[1];
			$new_signiture = hash_hmac($header->alg, $data, $secret);
			if ($new_signiture == $signiture) {
				return true;
			}else {
				return false;
			}
		}

		public function revoke_JWT($key) {
			$key = explode("-", $key);
			$new_key = uniqid("KY");
			$key[1] = $new_key;
			$key = implode("-", $key);
			return $key;
		}

		public function exp_check_JWT($jwt) {
			$payload = $this->get_payload($jwt);
			$now = time();

			if ($payload == null || !isset($payload->exp) || $now >= $payload->exp) {
				return true;
			}else {
				return false;
			}
		}

		public function check_renew($jwt) {
			$payload = $this->get_payload($jwt);
			$now = time();

			if ($payload == null || !isset($payload->rnw) || $payload->rnw < $now) {
				return true;
			}else {
				return false;
			}
		}

		public function insert_JWT($jwt) {
			setcookie("JWT", $jwt, time() + 2592000, "/", null, 0, 1);
		}

		public function remove_JWT() {
			setcookie("JWT", "", time() - 2592000, "/", null, 0, 1);
		}

		protected function get_payload($jwt) {
			$key = explode('.', $jwt);
			if (empty($key) || !isset($key[1])) {
				return null;
			}
			return $payload = $this->decode($key[1]);
		}

		protected function encode($data) {
			return base64_encode(json_encode($data));
		}

		protected function decode($data) {
			return json_decode(base64_decode($data));
		}

		protected function get_authorization_header(){
			$headers = null;
			if (isset($_SERVER['Authorization'])) {
				$headers = trim($_SERVER["Authorization"]);
			} else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
				$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
			} else if (function_exists('apache_request_headers')) {
				$request_headers = apache_request_headers();
				if (isset($request_headers['authorization'])) {
					$headers = $request_headers['authorization'];
				}else if (isset($request_headers['Authorization'])) {
					$headers = $request_headers['Authorization'];
				}
			}
			return $headers;
		}

		public function get_JWT_from_cookies() {
			return $_COOKIE['JWT'] ?? null;
		}

		public function get_bearer_token() {
			$headers = $this->get_authorization_header();
			if (!empty($headers)) {
				if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
					return $matches[1];
				}
			}
			return null;
		}
	}