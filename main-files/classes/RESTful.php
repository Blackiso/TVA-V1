<?php
	/**
	 * This is the main class for rest api application
	 * @return REST object
	 */
	class RESTful extends JWT {

		private $method;
		private $request_uri;
		private $request_headers;
		private $request_query;
		private $routes = array();
		public $user;

		function __construct() {
			$this->method = $_SERVER['REQUEST_METHOD'];
			$this->request_uri = explode('?', $_SERVER['REQUEST_URI'])[0];
			$this->request_headers = getallheaders();
		}

		public function route($method, $pattern, $callback, $auth_types = null, $auth = true) {
			$route = new route($method, $pattern, $this->request_uri, $callback, $auth_types, $auth);
			array_push($this->routes, $route);
		}

		public function init() {
			if ($this->method == "OPTIONS")  die();
			$not_found = false;
			for ($i = 0; $i < sizeof($this->routes); $i++) { 
				$route = $this->routes[$i];
			 	if ($route->get_uri_from_pattern() == $this->request_uri AND $this->method == $route->method) {
			 		if ($route->auth) {
			 			$this->authenticate();
			 			if (!$route->is_autorized($this->user->type)) {
				 			$this->unauthorized();
				 		}
			 		}
					call_user_func_array($route->callback, array($route, $this));
					$not_found = false;
					break;
				}else {
					$not_found = true;
				}
			}

			if ($not_found) {
				$this->not_found();
			}
		}
		
		protected function authenticate() {
			try {
				$jwt = $this->get_JWT_from_cookies();

				if ($jwt == null || $this->exp_check_JWT($jwt)) {
					throw new Exception();
				}

				$this->user = new User($jwt);
				$user_secret = $this->user->get_user_secret();

				if (!$user_secret || !$this->verify_JWT($jwt, $user_secret)) {
					throw new Exception();
				}

				if ($this->check_renew($jwt)) {
					$new_JWT = $this->user->renew_JWT();
					$this->insert_JWT($new_JWT);
				}

				if ($this->user->type !== "pending") {
					if ($this->user->check_expired_plan()) throw new Exception();
				}
			}catch (Exception $e) {
				$this->not_authenticated();
			}
		}

		public function new_authentication($jwt, $append = null) {
			$_jwt = explode('.', $jwt);
			$payload = $this->decode($_jwt[1]);
			$response = [
				"user_id" => $payload->uid,
				"email" => $payload->eml,
				"type" => $payload->typ,
				"name" => $payload->nam,
				"dev" => $jwt
			];
			if ($append !== null) {
				foreach ($append as $key => $value) {
					$response[$key] = $value;
				}
			}
			$this->response($response);
		}

		public function get_ip() {
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
	     		$ip = $_SERVER['HTTP_CLIENT_IP'];
		    }elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		     	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		    }else {
		     	$ip = $_SERVER['REMOTE_ADDR'];
		    }
		    return $ip;
		}

		public function json_strip_tags($array) {
			$str = json_encode($array);
			$str = strip_tags($str);
			$str = preg_replace("(['])", "", $str);
			return json_decode($str);
		}

		public function get_body() {
			$entity_body = file_get_contents('php://input');
			return json_decode($entity_body);
		}

		public function json($data) {
			return json_encode($data, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
		}

		public function throw_error($err, $code = null) {
			if (gettype($err) == "string") {
				$err = [
					"error" => $err,
					"exit" => false,
					"code" => $code
				];
			}
			if ($code !== null) http_response_code($code);
			exit($this->json($err));
		}

		public function not_found() {
			$err = [
				"error" => "Resource Not Found!",
				"exit"  => false,
				"code" => 404
			];
			$this->throw_error($err, 404);
		}

		public function not_authenticated() {
			$err = [
				"error" => "Authentication Required!",
				"exit"  => true,
				"code" => 401
			];
			$this->throw_error($err, 401);
		}

		public function unauthorized() {
			$err = [
				"error" => "Unauthorized Request!",
				"exit"  => false,
				"code" => 401
			];
			$this->throw_error($err, 401);
		}

		public function bad_request() {
			$err = [
				"error" => "Bad Request!",
				"exit"  => false,
				"code" => 400
			];
			$this->throw_error($err, 400);
		}

		public function created($data = null) {
			http_response_code(201);
			if ($data !== null) {
				echo $this->json($data);
			}
		}

		public function response($data = null) {
			if ($data !== null) {
				http_response_code(200);
				echo $this->json($data);
			}else {
				http_response_code(204);
			}
		}
	}