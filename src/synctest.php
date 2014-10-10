<?php

	$input = file_get_contents('php://input');
	//echo "<pre>\n";
	$input_decoded = json_decode($input);
	print_r ($input_decoded);
	//echo "</pre>\n";
?>