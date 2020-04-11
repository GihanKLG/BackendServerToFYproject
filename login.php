<?php
require_once 'db_user.php';
$action = process_request_and_get_action();
info(__FILE__, __FUNCTION__, __LINE__, $action, reset_pass($_REQUEST));
require_once 'session.php';

db_login($_REQUEST);
?>