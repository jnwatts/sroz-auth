<?php
http_response_code(500);
define("__AUTH_INTERNAL__", true);
set_exception_handler(function ($e) {
	error_log($e);
	die();
});
require(__DIR__."/auth.php");
http_response_code(validate_path());

