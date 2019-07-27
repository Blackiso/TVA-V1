<?php

	$allowed_users = array("regular", "premium", "user");

	$App->route('POST', '/resources/companies/:company-id/files', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$company_id = $route->params['company-id'];
		$master_id = $main_app->user->master_id;

		if (empty($data->file_name) || empty($data->year)) {
			$main_app->bad_request();
		}

		if (gettype($data->year) !== "integer") {
			$main_app->bad_request();
		}

		if (strlen($data->file_name) > 20) {
			$main_app->bad_request();
		}

		if ($data->type !== "quarterly" && $data->type !== "monthly") {
			$main_app->bad_request();
		}

		if (!$db->check_row("companies", array("id" => $company_id, "master_id" => $master_id))) {
			$main_app->not_found();
		}

		$data->id = $db->generate_unique_ids("files", "id")[0];
		$add_query = "INSERT INTO files (id, company_id, file_name, year, type) VALUES ($data->id, $company_id, '$data->file_name', $data->year, '$data->type')";
		$result = $db->query($add_query);
		if ($result->query) {
			$main_app->created($data);
		}

	}, $allowed_users);

	$App->route('GET', '/resources/companies/:company-id/files', function($route, $main_app) {

		$db = Database::get_instence();
		$company_id = $route->params['company-id'];
		$master_id = $main_app->user->master_id;
		$params = $route->get_query_params();
		$index = $params['i'] ?? 0;

		$company_query = "SELECT company_name FROM companies WHERE id = $company_id AND master_id = $master_id";
		$company = $db->query($company_query);
		if (empty($company)) {
			$main_app->not_found();
		}

		if (isset($params['s']) && $params['s'] !== "") {
			$s = explode(" ", urldecode($params['s']));
			$s = "%".implode("%", $s)."%";

			$get_query = "SELECT DISTINCT id, file_name, year, type, last_modified FROM files WHERE company_id = $company_id AND file_name LIKE '$s' ORDER BY id DESC";
		}else {
			$get_query = "SELECT id, file_name, year, type, last_modified FROM files WHERE company_id = $company_id ORDER BY id DESC";
		}
		
		$result = $db->pagination($get_query, $index);
		$result->company = array();
		$result->company['id'] = $company_id;
		$result->company['name'] = $company['company_name'];
		$main_app->response($result);

	}, $allowed_users);

	$App->route('GET', '/resources/companies/:company-id/files/:file-id', function($route, $main_app) {

		$db = Database::get_instence();
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$master_id = $main_app->user->master_id;
		$quarterly = [3,6,9,12];
		$monthly = [1,2,3,4,5,6,7,8,9,10,11,12];

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id, f.type FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_rs = $db->query($check_qr);

		if (empty($check_rs)) {
			$main_app->not_found();
		}

		$months = "SELECT f.id, f.type, count(b.id) AS bills_num, b.month FROM files AS f INNER JOIN bills AS b ON f.id = b.file_id WHERE f.id = $file_id GROUP BY b.month";
		$months = $db->query($months);
		$response = [
			"file_id" => $file_id,
			"months" => []
		];

		function search_me_array($arr, $mon) {
			foreach ($arr as $group) {
				if ($group['month'] === (string)$mon) {
					return $group;
				}
			}
			return null;
		}

		$myarr = $check_rs['type'] === "monthly" ? $monthly : $quarterly;
		foreach ($myarr as $num) {
			$obj = search_me_array($months, $num);
			$new = [
				"name" => date('F', mktime(0, 0, 0, $num, 10)),
				"month" => $num,
				"bills_num" => $obj['bills_num'] ?? 0
			];
			array_push($response['months'], $new);	
		}
		$main_app->response($response);

	}, $allowed_users);

	$App->route('PATCH', '/resources/companies/:company-id/files/:file-id', function($route, $main_app) {

		$db = Database::get_instence();
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$master_id = $main_app->user->master_id;
		$data = $main_app->get_body();
		$allowed_keys = array("file_name", "year");

		if (empty($data) || sizeof((array)$data) == 0) {
			$main_app->bad_request();
		}

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id, f.type FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_rs = $db->query($check_qr);

		if (empty($check_rs)) {
			$main_app->not_found();
		}

		$update_query = "UPDATE files SET";
		$i = 1;
		$size = sizeof((array)$data);
		foreach ($data as $key => $value) {
			if (in_array($key, $allowed_keys)) {
				$update_query .= " $key = '$value'";
				if($i < $size) $update_query .= " ,";
				$i++;
			}else {
				$main_app->bad_request();
			}
		}
		$update_query .= " WHERE id = $file_id AND company_id = $company_id";
		$result = $db->query($update_query);
		if ($result) {
			$main_app->response();
		}

	}, $allowed_users);

	$App->route('DELETE', '/resources/companies/:company-id/files/:file-id', function($route, $main_app) {

		$db = Database::get_instence();
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$master_id = $main_app->user->master_id;

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id, f.type FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_rs = $db->query($check_qr);

		if (empty($check_rs)) {
			$main_app->not_found();
		}

		$delete_query = "DELETE FROM files WHERE id = $file_id AND company_id = $company_id";
		$result = $db->query($delete_query);
		if ($result) {
			$main_app->response();
		}

	}, $allowed_users);