<?php

	class Database {

		private $db_user = "root";
		private $db_pass = "";
		private $db_host = "127.0.0.1";
		private $db_name = "tva_app";

		public static $instence;
		private $conn;

		public static function get_instence() {
			if (self::$instence == null) {
				self::$instence = new Database();
			}
			return self::$instence;
		}

		private function __construct() {
			try {
				// init PDO object
				$pdo_init = 'mysql:host='.$this->db_host;
				$pdo_init .= ';dbname='.$this->db_name;
			
				$this->conn = new PDO($pdo_init, $this->db_user, $this->db_pass);
				// Setup errors mod
				$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch (PDOException $e) {
			    $this->db_error($e->getMessage());
			}
		}

		public function query($statment) {
			try {
				if (gettype($statment) == "array") {
					$statment = implode(";", $statment);
					$statment .= ";";
					$query = $this->conn->prepare($statment);
					$rs =  $query->execute();
					return $rs;
				}

				$query = $this->conn->prepare($statment);
				$rs =  $query->execute();

				if (preg_match('/^(SELECT)/', $statment)) {
					$return = $query->fetchAll(PDO::FETCH_ASSOC);
					if (sizeof($return) == 1) $return = $return[0];
					return $return;
				}else if (preg_match('/^(INSERT)/', $statment)) {
					$result = (object)array();
					$result->insert_id = $this->conn->lastInsertId();
					$result->query = $rs;
					return $result;
				}else {
					return $rs;
				}
			} catch (PDOException $e) {
				$this->db_error($e->getMessage());
			}
		}

		public function pagination($query, $index = 0, $order = null, $max = 10) {
			$page_start = $index;
			$query = $query;
			if (preg_match('/(GROUP)/', $query)) {
				$last_part = preg_match('/(?<=FROM).*(?=GROUP)/', $query, $matches);
			}else {
				$last_part = preg_match('/(?<=FROM).*$/', $query, $matches);
			}
			
			$last_part = $matches[0];
			
			if ($order !== null) {
				$query .= " ORDER BY $order";
			}
			$query .= " LIMIT $page_start, $max";

			$qr_result = $this->query($query);
			if (array_keys($qr_result) !== range(0, count($qr_result) - 1) AND !empty($qr_result)) {
				$qr_result = array($qr_result);;
			}
			$result = (object)array();
			$result->data = $qr_result;
			$result->max_index = $this->query("SELECT count(*) as num FROM $last_part")['num'];
			return $result;
		}

		public function check_row($table, $conditions) {
			$qr = "SELECT * FROM $table WHERE ";
			$i = 0;
			foreach ($conditions as $key => $value) {
				$qr .= "$key = '$value' ";
				if ($i !== sizeof($conditions)-1) $qr .= "AND ";
				$i++;
			}
			
			$result = $this->query($qr);
			if (empty($result)) {
				return false;
			}else {
				return true;
			}
		}

		public function generate_unique_ids($table, $id_name, $num = 1) {
			$return = array();
			for ($i = 0; $i < $num; $i++) { 
				$id  = str_replace(".", "", microtime(true));
				array_push($return, $id);
				usleep(3 * 100);
			}
			$ids = implode(", ", $return);
			$check = "SELECT $id_name FROM $table WHERE $id_name IN ($ids)";
			$result = $this->query($check);
			if (!empty($result)) {
				$this->generate_unique_ids($num, $table, $id_name);
			}else {
				return $return;
			}
		}

		private function db_error($msg) {
			echo json_encode(array('DB Error' => $msg));
			exit();
		}
	}