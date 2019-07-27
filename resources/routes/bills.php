<?php

	$allowed_users = array("regular", "premium", "user");

	$App->route('POST', '/resources/companies/:company-id/files/:file-id/bills/month/:month', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$month = $route->params['month'];
		$master_id = $main_app->user->master_id;
		$allowed_keys = array("nfa", "ddf", "ndf", "iff", "ice", "dbs", "mht", "tau", "tva", "ttc", "mdp", "ddp", "file_id", "month", "id");
		$quarterly = [3,6,9,12];
		$monthly = [1,2,3,4,5,6,7,8,9,10,11,12];

		if (sizeof($data) > 5) {
			$main_app->bad_request();
		}

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->not_found();
		}

		$get_file_type = "SELECT type FROM files WHERE id = $file_id";
		$get_file_type = $db->query($get_file_type);
		$get_file_type = $get_file_type["type"];

		if (!in_array($month, $$get_file_type)) {
			$main_app->bad_request();
		}

		$ids = $db->generate_unique_ids("bills", "id", sizeof($data));
		$add_query_first = "INSERT INTO bills ";
		$add_query_second = "VALUES ";
		$z = 0;
		foreach ($data as $index => $bill) {
			$z++;
			$bill->id = $ids[$index];
			$bill->file_id = $file_id;
			$bill->month = $month;
			if (sizeof((array)$bill) !== sizeof($allowed_keys)) {
				$main_app->bad_request();
			}
			$qr_key = "(";
			$qr_val = "(";
			$i = 0;
			foreach ($bill as $key => $value) {
				$i++;
				if (in_array($key, $allowed_keys)) {
					$nxt = $i < sizeof((array)$bill) ? ", " : ")";
					$qr_key .= "$key".$nxt;
					$qr_val .= "'$value'".$nxt;
				}else {
					$main_app->bad_request();
				}
			}
			$sep = $z < sizeof($data) ? ", " : " ";
			if ($z == 1) $add_query_first .= $qr_key." ";
			$add_query_second .= $qr_val.$sep;
		}

		$add_qr = $add_query_first.$add_query_second;
		$result = $db->query($add_qr);
		if ($result->query) {
			$now =  date("Y-m-d H:i:s");
			$db->query("UPDATE files SET last_modified = '$now' WHERE id = $file_id");
			$main_app->created($data);
		}

	}, $allowed_users);

	$App->route('GET', '/resources/companies/:company-id/files/:file-id/bills/month/:month', function($route, $main_app) {

		$db = Database::get_instence();
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$month = $route->params['month'];
		$master_id = $main_app->user->master_id;
		$params = $route->get_query_params();
		$index = $params['i'] ?? 0;

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id, f.file_name FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->not_found();
		}

		if (isset($params['s']) && $params['s'] !== "") {
			$s = explode(" ", urldecode($params['s']));
			$s = "%".implode("%", $s)."%";

			$get_qr = "SELECT DISTINCT * FROM bills WHERE file_id = $file_id AND month = $month AND nfa LIKE '$s' ORDER BY id DESC";
		}else {
			$get_qr = "SELECT * FROM bills WHERE file_id = $file_id AND month = $month ORDER BY id DESC";
		}

		$result = $db->pagination($get_qr, $index);
		$result->file = array();
		$result->file['id'] = $file_id;
		$result->file['month'] = $month;
		$result->file['name'] = $check_result['file_name'];
		if ($result) {
			$main_app->response($result);
		}

	}, $allowed_users);

	$App->route('PATCH', '/resources/companies/:company-id/files/:file-id/bills/:bill-id', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$bill_id = $route->params['bill-id'];
		$master_id = $main_app->user->master_id;
		$allowed_keys = array("nfa", "ddf", "ndf", "iff", "ice", "dbs", "mht", "tau", "tva", "ttc", "mdp", "ddp");

		if (empty($data) || sizeof((array)$data) == 0) {
			$main_app->bad_request();
		}

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->not_found();
		}

		if (!$db->check_row("bills", array("id" => $bill_id, "file_id" => $file_id))) {
			$main_app->not_found();
		} 

		$update_qr = "UPDATE bills SET ";
		$i = 0;
		foreach ($data as $key => $value) {
			$i++;
			if (in_array($key, $allowed_keys)) {
				$sp = $i < sizeof((array) $data) ? ", " : " ";
				$update_qr .= "$key = '$value'".$sp;
			}else {
				$main_app->bad_request();
			}
		}
		$update_qr .= "WHERE id = $bill_id AND file_id = $file_id";
		$result = $db->query($update_qr);
		if ($result) {
			$main_app->response();
		}
		
	}, $allowed_users);

	$App->route('DELETE', '/resources/companies/:company-id/files/:file-id/bills/:bill-id', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$company_id = $route->params['company-id'];
		$file_id = $route->params['file-id'];
		$bill_id = $route->params['bill-id'];
		$master_id = $main_app->user->master_id;

		$check_qr = "SELECT c.id, c.master_id, f.id, f.company_id FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->bad_request();
		}

		if (!$db->check_row("bills", array("id" => $bill_id, "file_id" => $file_id))) {
			$main_app->not_found();
		} 

		$delete_qr = "DELETE FROM bills WHERE id = $bill_id AND file_id = $file_id";
		$result = $db->query($delete_qr);
		if ($result) {
			$main_app->response();
		}
		
	}, $allowed_users);

	$App->route('GET', '/resources/bills/suppliers', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$master_id = $main_app->user->master_id;
		$params = $route->get_query_params();
		$search = $params['name'] ?? $main_app->bad_request();
		$s = explode(" ", urldecode($search));
		$s = "%".implode("%", $s)."%";

		$get_sup = "SELECT DISTINCT b.ndf, b.iff, b.ice FROM bills AS b INNER JOIN files AS f ON b.file_id = f.id INNER JOIN companies AS c ON f.company_id = c.id WHERE c.master_id = $master_id AND b.ndf LIKE '$s'";
		$result = $db->pagination($get_sup);
		if ($result) {
			$main_app->response($result);
		}
		
	}, $allowed_users);