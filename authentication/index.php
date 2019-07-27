<?php 

	require '../main-files/headers.php';

	$App = new RESTful();

	$App->route('POST', '/authentication/register', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->get_body();
		
		if (!isset($data->email) || !isset($data->name) || !isset($data->password)) {
			$main_app->bad_request();
		}

		if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
			$main_app->throw_error("Invalid Email Address!");
		}

		if ($db->check_row('users_details', array("email" => $data->email))) {
			$main_app->throw_error("Email Address Already Used!");
		}

		if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z\d@$!%*#?é^&â ]{5,20}$/', $data->password)) {
			$main_app->throw_error("Password Requires a Mix of Numbers and Letters Min Characters 5 and Max Characters 20");
		}

		$data->name = addslashes(strip_tags($data->name));

		if (empty($data->name) || $data->name == "") {
			$main_app->throw_error("Invalid Name!");
		}

		$user_ip = $main_app->get_ip();
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$account_type = "premium";

		$u_data = [
			"id" => null,
			"master_id" => null,
			"email" => $data->email,
			"type" => $account_type,
			"name" => $data->name,
			"block" => null,
		];

		$user = new User($u_data);
		$user_secret = $user->generate_user_secret($data->email);
		$user_secret_hash = explode("-", $user_secret)[0];
		$password_hash = hash_hmac('sha256', $data->password, $user_secret_hash);

		$register_query = "INSERT INTO users_details (name, email, password, account_type, secret, user_ip, user_agent)
			VALUES ('$data->name', '$data->email', '$password_hash', '$account_type', '$user_secret', '$user_ip', '$user_agent')";
		$register = $db->query($register_query);
		if ($register->query) {
			$user->id = $register->insert_id;
			$user->master_id = $register->insert_id;
			$user->secret = $user_secret;
			$jwt = $user->generate_jwt(false);
			$main_app->insert_JWT($jwt);
			$main_app->new_authentication($jwt);
		}

	}, null, false);

	$App->route('POST', '/authentication/login', function($route, $main_app) {

		function login_error($main_app) {
			$main_app->throw_error("Wrong Email or Password!");
		}

		$data = $main_app->get_body();
		$db = Database::get_instence();

		if (!isset($data->email) || !isset($data->password)) {
			$main_app->bad_request();
		}

		if (preg_match('/(\.edg)$/', $data->email)) {
			$login_query = "SELECT sub_user_id, master_id, companies, email, name, password, secret, blocked FROM sub_users
			WHERE email = '$data->email'";
			$secret = 'secret';
			$id = 'sub_user_id';
			$master_id = 'master_id';
			$name = 'name';
			$blocked = 'blocked';
		}else {
			$login_query = "SELECT user_id, name, email, password, account_type, secret FROM users_details
			WHERE email = '$data->email'";
			$secret = 'secret';
			$id = 'user_id';
			$name = 'name';
			$type = 'account_type';
		}

		$login = $db->query($login_query);
		if (empty($login)) {
			login_error($main_app);
		}

		if (isset($login['blocked']) AND $login['blocked'] == 1) {
			$main_app->throw_error("Your Account is Blocked!");
		}

		$user_secret_hash = explode("-", $login[$secret])[0];
		$login_password = hash_hmac('sha256', $data->password, $user_secret_hash);
		if ($login_password !== $login['password']) {
			login_error($main_app);
		}

		$data = [
			"id" => $login[$id],
			"master_id" => isset($master_id) ? $login[$master_id] : $login[$id],
			"email" => $login['email'],
			"type" => isset($type) ? $login[$type] : "user",
			"name" => isset($name) ? $login[$name] : null,
			"block" => isset($blocked) ? filter_var($login[$blocked], FILTER_VALIDATE_BOOLEAN) : null,
		];

		$append = isset($login['companies']) ? ['companies' => explode(',', $login['companies'])] : null;

		$user = new User($data);
		$jwt = $user->generate_jwt();
		$main_app->insert_JWT($jwt);
		$main_app->new_authentication($jwt, $append);

	}, null, false);

	$App->route('POST', '/authentication/logout', function($route, $main_app) {

		if ($main_app->user->revoke_key()) {
			$main_app->remove_JWT();
			$main_app->response();
		}

	});

	$App->route('GET', '/authentication/authenticate', function($route, $main_app) {
		
		$jwt = $main_app->get_JWT_from_cookies();
		$main_app->new_authentication($jwt);

	});

	$App->init();