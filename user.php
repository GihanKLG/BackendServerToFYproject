<?php
require_once 'db_user.php';
$action = process_request_and_get_action();
info(__FILE__, __FUNCTION__, __LINE__, $action, reset_pass($_REQUEST));
require_once 'session.php';

switch ($action) {
  case ACTION_READ:
    db_user_read($_REQUEST);
    break;
  case ACTION_ADD:
    db_user_add($_REQUEST);
    break;  
  case ACTION_MOD:
    update_user_profile($_REQUEST);
    break;
  case ACTION_MOD_IMAGE:
    update_user_image($_REQUEST);
    break;
  default:
    exit(fail_return(ERR_UNKNOWN_ACTION, false));
}

?>