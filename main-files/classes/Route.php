<?php

	class Route {

		public $method;
		public $params;
		public $callback;
		public $auth;

		function __construct($method, $pattern, $uri, $callback, $auth_types, $auth) {
			$this->method = $method;
			$this->uri = $uri;
			$this->pattern = $pattern;
			$this->callback = $callback;
			$this->params = $this->extract_patterns($pattern, $uri);
			$this->auth_types = $auth_types;
			$this->auth = $auth;
		}

		private function extract_patterns($pattern, $uri) {
			$params = array();
			$pattern = explode('/', $pattern);
			$uri = explode('/', $uri);
			if (sizeof($pattern) !== sizeof($uri)) {
				return null;
			}
			foreach ($pattern as $i => $elemnt) {
				if (preg_match('/^(:)/', $elemnt)) {
					$key = substr($elemnt, 1);
					$params[$key] = $uri[$i];
				}
			}
			return $params;
		}

		public function get_query_params() {
			$request_query = explode('&', $_SERVER['QUERY_STRING']);
			if (empty($request_query) || $request_query[0] == "") {
				return false;
			}
			
			$arr = array();
			foreach ($request_query as $qr) {
				$split_qr = explode('=', $qr);
				$arr[$split_qr[0]] = $split_qr[1] ?? null;
			}
			return $arr;
		}

		public function is_autorized($type) {
			if ($this->auth_types == null) {
				return true;
			}

			foreach ($this->auth_types as $_type) {
				if ($_type == $type) {
					return true;
				}
			}

			return false;
		}

		public function get_uri_from_pattern() {
			$uri_array = explode('/', $this->uri);
			$pattern_array = explode('/', $this->pattern);
			$return = array();
			if (sizeof($uri_array) !== sizeof($pattern_array)) {
				return null;
			}
			foreach ($pattern_array as $i => $value) {
				if (preg_match('/^(:)/', $value)) {
					array_push($return, $uri_array[$i]);
				}else {
					array_push($return, $value);
				}
			}
			$return = implode('/', $return);
			return $return;
		}
	}