<?php

	$allowed_users = array("regular", "premium", "user");

	$App->route('POST', '/resources/companies', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());

		if (!isset($data->company_name) || !isset($data->activity) || !isset($data->i_f) || !isset($data->phone) || !isset($data->address)) {
			$main_app->bad_request();
		}

		$id = $main_app->user->master_id;
		$company_name = $data->company_name;
		$activity = $data->activity;
		$address = $data->address;

		if (strlen($data->i_f) > 8) {
			$main_app->bad_request();
		}

		$add_query = "INSERT INTO companies (master_id, company_name, activity, i_f, phone, address)
			VALUES ($id, '$company_name', '$activity', '$data->i_f', '$data->phone', '$address')";
		$result = $db->query($add_query);
		if ($result->query) {
		 	$data->id = $result->insert_id;
		 	unset($data->phone);
		 	unset($data->address);
		 	$main_app->created($data);
		}

	}, $allowed_users);

	$App->route('GET', '/resources/companies', function($route, $main_app) {

		$db = Database::get_instence();
		$params = $route->get_query_params();
		$index = $params['i'] ?? 0;
		$id = $main_app->user->master_id;

		if (isset($params['s']) && $params['s'] !== "") {
			$s = explode(" ", urldecode($params['s']));
			$s = "%".implode("%", $s)."%";

			$get_query = "SELECT DISTINCT id, company_name, activity, i_f FROM companies WHERE master_id = $id AND company_name LIKE '$s' ORDER BY id DESC";
		}else {
			$get_query = "SELECT id, company_name, activity, i_f FROM companies WHERE master_id = $id ORDER BY id DESC";
		}
		
		$result = $db->pagination($get_query, $index);
		$main_app->response($result);

	}, $allowed_users);

	$App->route('GET', '/resources/companies/:company-id', function($route, $main_app) {

		$db = Database::get_instence();
		$master_id = $main_app->user->master_id;
		$company_id = $route->params['company-id'] ?? null;

		if (!isset($company_id) || empty($company_id)) {
			$main_app->bad_request();
		}

		$get_query = "SELECT id, company_name, activity, i_f, phone, address FROM companies WHERE id = $company_id AND master_id = $master_id";
		$result = $db->query($get_query);

		if (empty($result)) {
			$main_app->not_found();
		}

		if ($result) {
			$main_app->response($result);
		}

	}, $allowed_users);

	$App->route('PATCH', '/resources/companies/:company-id', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$master_id = $main_app->user->master_id;
		$company_id = $route->params['company-id'] ?? null;
		$allowed_keys = array("company_name", "activity", "i_f", "phone", "address");

		if (empty($data) || sizeof((array)$data) == 0) {
			$main_app->bad_request();
		}

		if (!isset($company_id) || empty($company_id)) {
			$main_app->bad_request();
		}

		if (!$db->check_row("companies", array("id" => $company_id, "master_id" => $master_id))) {
			$main_app->not_found();
		}

		$update_query = "UPDATE companies SET";
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
		$update_query .= " WHERE id = $company_id AND master_id = $master_id";
		$result = $db->query($update_query);
		if ($result) {
		 	$main_app->response();
		}
		
	}, $allowed_users);

	$App->route('DELETE', '/resources/companies/:company-id', function($route, $main_app) {

		$db = Database::get_instence();
		$master_id = $main_app->user->master_id;
		$company_id = $route->params['company-id'] ?? null;

		if (!isset($company_id) || empty($company_id)) {
			$main_app->bad_request();
		}

		if (!$db->check_row("companies", array("id" => $company_id, "master_id" => $master_id))) {
			$main_app->not_found();
		}

		$dlt_query = "DELETE FROM companies WHERE id = $company_id AND master_id = $master_id";
		$result = $db->query($dlt_query);
		if ($result) {
			$main_app->response();
		}

	}, $allowed_users);