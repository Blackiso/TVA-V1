<?php 

	require '../main-files/headers.php';
	require 'classes/httpClient.php';
	require 'classes/paypal.php';

	$App = new RESTful();
	$allowed_users = array("regular", "pending", "premium");

	$App->route('POST', '/payment/create', function($route, $main_app) {

		$paypal = new PayPal();
		$db = Database::get_instence();
		$data = $main_app->json_strip_tags($main_app->get_body());
		$reference_id = $data->reference_id ?? $main_app->bad_request();

		$query_plans = "SELECT * FROM account_plans WHERE reference_id = '$reference_id'";
		$result = $db->query($query_plans);

		if (empty($result)) {
			$main_app->not_found();
		}

		if (isset($data->months) AND $data->months !== 0) {
			$result['price'] = $result['price'] * $data->months;
		}

		$body = [
			"intent" => "CAPTURE",
			"purchase_units" => [
				[	
					"reference_id" => $reference_id,
					"amount" => [
						"value" => $result['price'],
						"currency_code" => $result['currency_code']
					],
				]
			],
			"application_context" => [
				"cancel_url" => $paypal->cancel_url,
				"return_url" => $paypal->return_url
			] 
		];

	    $body = json_encode($body);
		$result = json_decode($paypal->create_order($body));
		$approve_url = preg_replace('/({token})/', $result->id, $paypal->approve_url);
		$return = [
			"id" => $result->id,
			"approve" => $approve_url,
			"status" => $result->status
		];

		$main_app->response($return);

	}, $allowed_users);

	$App->route('GET', '/payment/capture', function($route, $main_app) {

		$paypal = new PayPal();
		$db = Database::get_instence();
		$params = $route->get_query_params();
		$token = $params['token'];
		$master_id = $main_app->user->master_id;

		if ($db->check_row("payments", array("payment_id" => $token, "master_id" => $master_id))) {
			$main_app->throw_error("Payment Already Captured!");
		}

		$captured = json_decode($paypal->capture_order($token));
		if (isset($captured->name) AND $captured->name == "UNPROCESSABLE_ENTITY") {
			$main_app->throw_error("Payment Error!");
		}

		if ($captured->status == "COMPLETED") {
			$payment_id = $captured->id;
			$capture_id = $captured->purchase_units[0]->payments->captures[0]->id;
			$master_id  = $main_app->user->master_id;
			$payment_amount = $captured->purchase_units[0]->payments->captures[0]->amount->value;
			$payment_currency = $captured->purchase_units[0]->payments->captures[0]->amount->currency_code;
			$reference_id = $captured->purchase_units[0]->reference_id;
			$account_type = $db->query("SELECT type FROM account_plans WHERE reference_id = '$reference_id'")['type'];
			$full_name = $captured->purchase_units[0]->shipping->name->full_name;
			$payer_email = $captured->payer->email_address;
			$payer_id = $captured->payer->payer_id;
			$base_price = $db->query("SELECT price FROM account_plans WHERE reference_id = '$reference_id'")['price'];
			$duration = $payment_amount / $base_price;
			$expire_date = strtotime("+$duration months");
			$expire_date = date("Y-m-d H:i:s", $expire_date);

			$db->query("DELETE FROM active_plans WHERE master_id = '$master_id'");

			$insert_payment = "INSERT INTO payments (payment_id, capture_id, master_id, payment_amount, payment_currency, account_type, full_name, payer_email, payer_id) VALUES ('$payment_id', '$capture_id', '$master_id', '$payment_amount', '$payment_currency', '$account_type', '$full_name', '$payer_email', '$payer_id')";
			$update_account = "UPDATE users_details SET account_type = '$account_type' WHERE user_id = $master_id";
			$insert_plan = "INSERT INTO active_plans (master_id, payment_id, reference_id, duration, expire_date) VALUES ('$master_id', '$payment_id', '$reference_id', $duration, '$expire_date')";

			$all_querys = array($insert_payment, $update_account, $insert_plan);
			$exec = $db->query($all_querys);
			if ($exec) {
				$main_app->user->type = $account_type;
				$jwt = $main_app->user->generate_jwt();
				$main_app->insert_JWT($jwt);
				$main_app->new_authentication($jwt);
			}else {
				$main_app->throw_error("Payment Error!");
			}
		}

	}, $allowed_users);

	$App->route('POST', '/payment/refund/:payment-id', function($route, $main_app) {
			
		$paypal = new PayPal();
		$db = Database::get_instence();

		$payment_id = $route->params['payment-id'];
		$account_type = "pending";
		$master_id = $main_app->user->master_id;
		$payment_info = $db->query("SELECT capture_id, payment_time, refunded FROM payments WHERE payment_id = '$payment_id'");
		$capture_id = $payment_info['capture_id'];

		if ($payment_info['refunded']) {
			$main_app->throw_error("Payment Already Refunded!");
		}
		
		$payment_time = strtotime($payment_info['payment_time']) + (7 * 24 * 60 * 60);
		$time_now = time();
		if ($payment_time < $time_now) {
			$main_app->throw_error("Ineligible for Refund!");
		}

		$paypal_rs = json_decode($paypal->refund($capture_id));
		$add_refund = "INSERT INTO refunds (refund_id, master_id, payment_id) VALUES ('$paypal_rs->id', '$master_id', '$payment_id')";
		$update_payments = "UPDATE payments SET refunded = 1 WHERE payment_id = '$payment_id'";
		$update_users = "UPDATE users_details SET account_type = '$account_type' WHERE user_id = $master_id";

		$querys = array($add_refund, $update_payments, $update_users);
		$result = $db->query($querys);
		if ($result) {
			$main_app->user->type = $account_type;
			$jwt = $main_app->user->generate_jwt();
			$main_app->insert_JWT($jwt);
			$refund = (object) array();
			$refund->id = $paypal_rs->id;
			$refund->status = $paypal_rs->status;
			$append = array('refund' => $refund);
			$main_app->new_authentication($jwt, $append);
		}else {
			$main_app->throw_error("Error Refunding!");
		}

	}, $allowed_users);

	$App->route('GET', '/payment/plans', function($route, $main_app) {
			
		$db = Database::get_instence();
		$get_palns = "SELECT * FROM account_plans";
		$result = $db->query($get_palns);
		$main_app->response($result);
		
	}, null, false);

	$App->route('POST', '/payment/upgrade', function($route, $main_app) {
			
		$db = Database::get_instence();
		
		
	}, ["regular"]);

	$App->init();




