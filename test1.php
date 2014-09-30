<?php

    require_once "vendor/autoload.php";

	$ari=parse_ini_file("/etc/ari.ini");

    $conn       = new phpari($ari['ARI_USERNAME'], $ari['ARI_PASSWORD'], "hello-world", $ari['ARI_SERVER'], $ari['ARI_PORT'], $ari['ARI_ENDPOINT']);
	print_r($conn);
    $cEndPoints = new endpoints($conn);
    $response   = $cEndPoints->endpoints_list();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit(0);


?>
