<?php

	$allowed_users = array("regular", "premium");

	$App->route('PATCH', '/resources/account', function($route, $main_app) {

		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$allowed_keys = ['email', 'password', 'name'];
		$user_id = $main_app->user->id;

		if (empty($data) || sizeof((array)$data) == 0 || !isset($data->old_password)) {
			$main_app->bad_request();
		}

		$user_details = "SELECT password, secret FROM users_details WHERE user_id = $user_id";
		$user_details = $db->query($user_details);
		$user_secret = explode('-', $user_details['secret'])[0];
		$old_password_hash = hash_hmac('sha256', $data->old_password, $user_secret);
		if ($old_password_hash !== $user_details['password']) {
			$main_app->throw_error('Password Error!');
		}

		if (isset($data->password)) {
			if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z\d@$!%*#?é^&â ]{5,20}$/', $data->password)) {
				$main_app->throw_error("Password Requires a Mix of Numbers and Letters Min Characters 5 and Max Characters 20");
			}
			$data->password = hash_hmac('sha256', $data->password, $user_secret);
			if ($data->password === $user_details['password']) {
				$main_app->throw_error("You can't use the same Password!");
			}
		}

		if (isset($data->email)) {
			if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
				$main_app->throw_error("Invalid Email Address!");
			}
		}

		if (isset($data->name)) {
			$data->name = addslashes(strip_tags($data->name));
		}

		unset($data->old_password);
		$update_query = "UPDATE users_details SET";
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
		$update_query .= " WHERE user_id = $user_id";
		$result = $db->query($update_query);
		if ($result) {
			$jwt = $main_app->user->generate_jwt();
			$main_app->insert_JWT($jwt);
			$main_app->response();
		}

	}, $allowed_users);