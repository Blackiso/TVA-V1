<?php 

	$allowed_users = array("regular", "premium");

	$App->route('POST', '/resources/sub-users/create', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->get_body();
		$master_id = $main_app->user->master_id;

		if (!isset($data->email) || !isset($data->password) || !isset($data->company_id) || !isset($data->name)) {
			$main_app->bad_request();
		}

		if (!preg_match('/(\.edg)$/', $data->email)) {
			$main_app->throw_error("Invalid Email Address!");
		}

		if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
			$main_app->throw_error("Invalid Email Address!");
		}

		if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z\d@$!%*#?é^&â ]{5,20}$/', $data->password)) {
			$main_app->throw_error("Password Requires a Mix of Numbers and Letters Min Characters 5 and Max Characters 20");
		}

		if ($db->check_row("sub_users", array("email" => $data->email))) {
			$main_app->throw_error("Invalid Email Address!");
		}

		if (!$db->check_row("companies", array("id" => $data->company_id, "master_id" => $master_id))) {
			$main_app->not_found();
		}

		$data->name = addslashes(strip_tags($data->name));
		$sub_user_secret = $main_app->user->generate_user_secret($data->email);
		$sub_user_secret_hash = explode("-", $sub_user_secret)[0];
		$password_hash = hash_hmac('sha256', $data->password, $sub_user_secret_hash);
		$companies = $data->company_id;

		$add_sub_user = "INSERT INTO sub_users (master_id, companies, email, name, password, secret)
			VALUES ($master_id, '$companies', '$data->email', '$data->name', '$password_hash', '$sub_user_secret')";
		$result = $db->query($add_sub_user);
		if ($result->query) {
			$return = (object) array();
			$return->id = $result->insert_id;
			$return->email = $data->email;
			$return->name = $data->name;
			$return->blocked = false;
			$main_app->created($return);
		}

	}, $allowed_users);

	$App->route('POST', '/resources/sub-users/add', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->get_body();
		$master_id = $main_app->user->master_id;

		if (!isset($data->email) || !isset($data->company_id)) {
			$main_app->bad_request();
		}

		if (!$db->check_row("sub_users", array("email" => $data->email))) {
			$main_app->throw_error("Invalid Email Address!");
		}

		if (!$db->check_row("companies", array("id" => $data->company_id, "master_id" => $master_id))) {
			$main_app->not_found();
		}

		$check = "SELECT sub_user_id FROM sub_users WHERE companies LIKE '%$data->company_id,%' OR companies LIKE '%,$data->company_id%' OR companies LIKE '%$data->company_id%'";

		if (!empty($db->query($check))) {
			$main_app->throw_error("User already added!");
		}

		$add = "UPDATE sub_users SET companies = concat(companies,',$data->company_id') WHERE email = '$data->email'";

		$result = $db->query($add);
		if ($result) {
			$main_app->response();
		}

	});

	$App->route('GET', '/resources/sub-users', function($route, $main_app) {

		$db = Database::get_instence();
		$user_id = $main_app->user->master_id;
		$params = $route->get_query_params();
		$company_id = $params['company'] ?? null;
		$index = $params['i'] ?? 0;

		if ($company_id !== null) {
			$get_users = "SELECT sub_user_id, companies, email, name, blocked FROM sub_users WHERE master_id = $user_id AND companies LIKE '%$company_id,%' OR companies LIKE '%,$company_id%' OR companies LIKE '%$company_id%' ORDER BY sub_user_id DESC";
		}else {
			$get_users = "SELECT sub_user_id, companies, email, name, blocked FROM sub_users WHERE master_id = $user_id ORDER BY sub_user_id DESC";
		}
		$results = $db->pagination($get_users, $index);

		foreach ($results->data as $i => $result) {
			$results->data[$i]['companies'] = explode(',', $results->data[$i]['companies']);
			$results->data[$i]['blocked'] = filter_var($result['blocked'], FILTER_VALIDATE_BOOLEAN);			
		}
		
		$main_app->response($results);

	}, $allowed_users);

	$App->route('PATCH', '/resources/sub-users/:sub-user-id', function($route, $main_app) {

		$db = Database::get_instence();
		$user_id = $main_app->user->master_id;
		$sub_user_id = $route->params['sub-user-id'];
		$data = $main_app->get_body();
		$update = "";

		if (!isset($data->email) && !isset($data->password) && !isset($data->name)) {
			$main_app->bad_request();
		}

		if (!$db->check_row("sub_users", array("sub_user_id" => $sub_user_id, "master_id" => $user_id))) {
			$main_app->not_found();
		}

		if (isset($data->email)) {
			if (!preg_match('/(\.edg)$/', $data->email)) {
				$main_app->throw_error("Invalid Email Address!");
			}

			if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
				$main_app->throw_error("Invalid Email Address!");
			}

			if ($db->check_row("sub_users", array("email" => $data->email))) {
				$main_app->throw_error("Invalid Email Address!");
			}

			$update .= " email = '$data->email'";
			if (count((array)$data) > 1) $update .= ",";
		}

		if (isset($data->password)) {
			if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z\d@$!%*#?é^&â ]{5,20}$/', $data->password)) {
				$main_app->throw_error("Password Requires a Mix of Numbers and Letters Min Characters 5 and Max Characters 20");
			}
			$sub_user_secret = $db->query("SELECT secret FROM sub_users WHERE sub_user_id = $sub_user_id");
			$sub_user_secret = $sub_user_secret['secret'];
			$sub_user_secret_hash = explode("-", $sub_user_secret)[0];

			$password_hash = hash_hmac('sha256', $data->password, $sub_user_secret_hash);
			$update .= " password = '$password_hash'";
			if (count((array)$data) > 1) $update .= ",";
		}


		if (isset($data->name)) {
			$data->name = addslashes(strip_tags($data->name));
			$update .= " name = '$data->name'";
		}

		$update_query = "UPDATE sub_users SET $update WHERE sub_user_id = $sub_user_id AND master_id = $user_id";
		$result = $db->query($update_query);
		if ($result) {
			$sub_user = new User(null, $sub_user_id);
			$sub_user->revoke_key();
			$main_app->response();
		}

	}, $allowed_users);

	$App->route('DELETE', '/resources/sub-users/:sub-user-id', function($route, $main_app) {

		$db = Database::get_instence();
		$user_id = $main_app->user->master_id;
		$sub_user_id = $route->params['sub-user-id'];

		if (!$db->check_row("sub_users", array("sub_user_id" => $sub_user_id, "master_id" => $user_id))) {
			$main_app->not_found();
		}

		$delete_query = "DELETE FROM sub_users WHERE sub_user_id = $sub_user_id AND master_id = $user_id";
		$result = $db->query($delete_query);
		if ($result) {
			$main_app->response();
		}

	}, $allowed_users);

	$App->route('POST', '/resources/sub-users/:sub-user-id/:block', function($route, $main_app) {

		$db = Database::get_instence();
		$user_id = $main_app->user->master_id;
		$sub_user_id = $route->params['sub-user-id'];
		$block = $route->params['block'];

		if (!$db->check_row("sub_users", array("sub_user_id" => $sub_user_id, "master_id" => $user_id))) {
			$main_app->not_found();
		}

		if ($block == "block") {
			$block = 1;
		}else if ($block == "unblock") {
			$block = 0;
		}else {
			$main_app->bad_request();
		}

		$update_query = "UPDATE sub_users SET blocked = $block WHERE sub_user_id = $sub_user_id AND master_id = $user_id";
		$result = $db->query($update_query);
		if ($result) {
			$sub_user = new User(null, $sub_user_id);
			$sub_user->revoke_key();
			$main_app->response();
		}

	}, $allowed_users);
