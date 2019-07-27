
<?php 

	require '../main-files/headers.php';

	$App = new RESTful();

	$routes = scandir(__DIR__."/routes");
	foreach ($routes as $route) {
		if (preg_match('/(\.php)$/', $route)) {
			require_once(__DIR__."/routes/".$route);
		}
	}

	$App->init();