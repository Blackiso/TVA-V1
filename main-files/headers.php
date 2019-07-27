<?php 
	header('Content-Type: application/json');
	header("Access-Control-Allow-Credentials: true");
	header("Access-Control-Allow-Origin: http://localhost");
	header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
	header("Access-Control-Allow-Headers: Authorization, *");

	require '../main-files/classes/Database.php';
	require '../main-files/classes/Route.php';
	require '../main-files/classes/JWT.php';
	require '../main-files/classes/RESTful.php';
	require '../main-files/classes/User.php';
	require '../main-files/classes/XML.php';
	require '../main-files/classes/fpdf.php';
	require '../main-files/classes/PDF.php';