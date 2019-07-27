<?php

	$allowed_users = array("regular", "premium", "user");

	$App->route('GET', '/resources/download/xml/:file-name', function($route, $main_app) {

		$payment_mode = [
			"espèce" => 1,
			"cheque" => 2,
			"prélèvement" => 3,
			"virement" => 4,
			"effet" => 5,
			"compensation" => 6,
			"autres" => 7
		];

		$db = Database::get_instence();
		$file_name_full = $route->params['file-name'];
		$file_name_parts = explode('.', $file_name_full);
		$file_name = $file_name_parts[0];
		$exstention = $file_name_parts[1];

		if (strtolower($exstention) !== "xml") {
			$main_app->throw_error("Invalid File Extention!");
		}

		$file_name = explode('-', $file_name);
		if (sizeof($file_name) !== 3) {
			$main_app->throw_error("Invalid File Name!");
		}
		$master_id = $main_app->user->master_id;
		$company_id = $file_name[0];
		$file_id = $file_name[1];
		$month = $file_name[2];

		$check_qr = "SELECT c.id, c.master_id, c.i_f, f.id, f.company_id, f.year, f.type FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->bad_request();
		}

		$type = $check_result['type'] == "monthly" ? 1 : 2;
		$year = $check_result['year'];
		$i_f  = $check_result['i_f'];

		$get_bills = "SELECT * FROM bills WHERE month = '$month' AND file_id = '$file_id'";
		$bills = $db->query($get_bills);

		$array = [
			"identifiantFiscal" => $i_f,
			"annee" => $year,
			"periode" => $month,
			"regime" => $type,
			"releveDeductions" => [
				"rd" => []
			]
		];

		foreach ($bills as $i => $bill) {
			$arr = [];
			$arr['ord'] = $i+1;
			$arr['num'] = $bill['nfa'];
			$arr['des'] = $bill['dbs'];
			$arr['mht'] = $bill['mht'];
			$arr['tva'] = $bill['tva'];
			$arr['ttc'] = $bill['ttc'];
			$arr['refF'] = [
				"if" => $bill['iff'],
				"nom" => $bill['ndf'],
				"ice" => $bill['ice']
			];
			$arr['tx'] = $bill['tau'];
			$arr['mp'] = [
				"id" => in_array($bill['mdp'], $payment_mode) ? $payment_mode[$bill['mdp']] : 7
			];
			$arr['dpai'] = $bill['ddp'];
			$arr['dfac'] = $bill['ddf'];
			array_push($array['releveDeductions']['rd'], $arr);
		}

		$xml = new LaLit\Array2XML();
		$xx = $xml::createXML("DeclarationReleveDeduction", $array);
		$xx = str_replace('<?xml version="1.0" encoding="utf-8" standalone="no"?>', '', $xx->saveXML());
		header('Content-Type: application/xml');
		header('Content-Disposition: attachment; filename="'.$file_name_full.'"');
		echo $xx;

	}, $allowed_users);

	$App->route('GET', '/resources/download/pdf/:file-name', function($route, $main_app) {

		$db = Database::get_instence();
		$file_name_full = $route->params['file-name'];
		$file_name_parts = explode('.', $file_name_full);
		$file_name = $file_name_parts[0];
		$exstention = $file_name_parts[1];

		if (strtolower($exstention) !== "pdf") {
			$main_app->throw_error("Invalid File Extention!");
		}

		$file_name = explode('-', $file_name);
		if (sizeof($file_name) !== 3) {
			$main_app->throw_error("Invalid File Name!");
		}
		$master_id = $main_app->user->master_id;
		$company_id = $file_name[0];
		$file_id = $file_name[1];
		$month = $file_name[2];

		$check_qr = "SELECT c.id, c.master_id, c.i_f, f.id, c.company_name, f.company_id, f.year, f.type FROM companies AS c INNER JOIN files AS f ON c.id = f.company_id WHERE c.master_id = $master_id AND c.id = $company_id AND f.id = $file_id";
		$check_result = $db->query($check_qr);
		if (empty($check_result)) {
			$main_app->bad_request();
		}

		$get_bills = "SELECT * FROM bills WHERE month = '$month' AND file_id = '$file_id'";
		$bills = $db->query($get_bills);
		if (empty($bills)) {
			$main_app->throw_error("Empty file!");
		}

		$type = $check_result['type'] == "monthly" ? 1 : 2;
		$i_f  = $check_result['i_f'];
		$data = [
			"year" => $check_result['year'],
			"month" => $check_result['type'] == "monthly" ? $month : 0,
			"tr" => $check_result['type'] == "quarterly" ? $month/3 : 0,
			"if" => $check_result['i_f'],
			"name" => $check_result['company_name']
		];
		$pdf = new PDF($data);
		$pdf->init($bills);

	}, $allowed_users);