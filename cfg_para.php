<?php
require_once 'db_cfg_para.php';
$action = process_request_and_get_action();
info(__FILE__, __FUNCTION__, __LINE__, $action, reset_pass($_REQUEST));
require_once 'session.php';

switch ($action) {
  case ACTION_READ:
    db_cfg_read($_REQUEST);
    break;
  default:
    exit(fail_return(ERR_UNKNOWN_ACTION, false));
}
?>