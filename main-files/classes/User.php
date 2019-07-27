<?php
	/**
	 * 
	 */
	class User extends JWT {

		public  $secret;
		public  $id;
		public  $matser_id;
		public  $name;
		public  $email;
		public  $type;
		public  $jwt;

		private $db;

		function __construct($data = null, $user_id = null) {
			if ($data !== null) {
				if (gettype($data) == "string") {
					$jwt = $data;
					$this->jwt = $jwt;
					$payload = explode('.', $this->jwt)[1];
					$payload = $this->decode($payload);
					
					$this->id = $payload->uid;
					$this->master_id = $payload->typ == "user" ? $payload->mid : $payload->uid;
					$this->name = $payload->nam ?? null;
					$this->email = $payload->eml;
					$this->type = $payload->typ;
				}else if (gettype($data) == "array") {
					$this->id = $data['id'];
					$this->master_id = $data['master_id'];
					$this->name = $data['name'];
					$this->email = $data['email'];
					$this->type = $data['type'];
					$this->block = $data['block'];
				}	
			}else {
				$this->id = $user_id;
				$this->type = "user";
			}
			$this->db = Database::get_instence();
		}

		public function get_user_secret() {
			if ($this->type == "user") {
				$qr = "SELECT secret FROM sub_users WHERE sub_user_id = '$this->id'";
				$secret = 'secret';
			}else {
				$qr = "SELECT secret FROM users_details WHERE user_id = '$this->id'";
				$secret = 'secret';
			}

			$result = $this->db->query($qr);
			if (empty($result)) {
				return false;
			}else {
				return $this->secret = $result[$secret];
			}
		}

		public function generate_jwt($rvk = true) {
			if ($rvk) $this->revoke_key();
			$payload = (object) array();
			$payload->uid = $this->id;
			$payload->mid = $this->master_id;
			$payload->nam = $this->name;
			$payload->eml = $this->email;
			$payload->typ = $this->type;
			return $this->sign_JWT($payload, $this->secret);
		}

		public function revoke_key() {
			if ($this->secret == null) {
				$this->get_user_secret();
			}
			$secret = $this->revoke_JWT($this->secret);
			$user_id = $this->id;

			if ($this->type == "user") {
				$query = "UPDATE sub_users SET secret = '$secret' WHERE sub_user_id = $user_id";
			}else {
				$query = "UPDATE users_details SET secret = '$secret' WHERE user_id = $user_id";
			}

			$result = $this->db->query($query);
			if ($result) {
				$this->secret = $secret;
				return true;
			}
		}

		public function renew_JWT() {
			return $this->generate_jwt();
		}

		public function check_expired_plan() {
			$check_query = "SELECT expire_date FROM active_plans WHERE master_id = $this->master_id";
			$result = $this->db->query($check_query);
			
			if (empty($result)) return false;

			$expire_time = strtotime($result['expire_date']);
			$now = time();

			if ($expire_time < $now) {
				$update_account = $this->db->query("UPDATE users_details SET account_type = 'pending' WHERE user_id = '$this->master_id'");
				$this->revoke_key();
				return true;
			}
			return false;
		}

		public function generate_user_secret($email) {
			$rand_num = time();
			$data = $email;
			$secret = hash_hmac('sha256', $data, $rand_num);
			$add_secret = uniqid("KY");

			return $secret."-".$add_secret;
		}
	}