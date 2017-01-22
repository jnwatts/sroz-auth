<?php
define("__AUTH_INCLUDE__", true);
define("__AUTH_SKIP_SESSION__", true);
$auth = require("./auth.php");

return $auth->phinx_config();