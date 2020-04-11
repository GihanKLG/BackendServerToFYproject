<?php
require_once 'util_notification.php';
$action = process_request_and_get_action();
info(__FILE__, __FUNCTION__, __LINE__, $action, reset_pass($_REQUEST));
require_once 'session.php';

switch ($action) { 
  case ACTION_READ:
    read_notification($_REQUEST);
    break;
  case ACTION_READ_OTHER:
    read_other_notification($_REQUEST);
    break;
  case ACTION_FIND:
    debug(__FILE__, __FUNCTION__, __LINE__, $_REQUEST);
    read_notification($_REQUEST);
    break;
  case ACTION_MOD:
    debug(__FILE__, __FUNCTION__, __LINE__, $_REQUEST);
    db_update_notification_status($_REQUEST);
    break;
  case ACTION_ADD:
    debug(__FILE__, __FUNCTION__, __LINE__, $_REQUEST);
    add_notification($_REQUEST);
    break;
  case ACTION_DEL:
    debug(__FILE__, __FUNCTION__, __LINE__, $_REQUEST);
    delete_notification($_REQUEST);
    break;
  default:
    exit(fail_return(ERR_UNKNOWN_ACTION, false));
}
?>