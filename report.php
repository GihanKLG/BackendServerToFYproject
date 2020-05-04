<?php
header("Access-Control-Allow-Origin: *");
require_once 'db_report.php';
$action = process_request_and_get_action();
info(__FILE__, __FUNCTION__, __LINE__, $action, reset_pass($_REQUEST));
require_once 'session.php';
require_once 'db.php';

switch ($action) {
  case ACTION_READ:
    db_read_location($_REQUEST);
    break;
  case "division_read":
    db_read_division($_REQUEST);
    break; 
  case "minimum_distance":
    db_read_minimum_division($_REQUEST);  
    break;
  case "district_count":
    db_read_district_count($_REQUEST); 
   break; 
  default:
    exit(fail_return(ERR_UNKNOWN_ACTION, false));
}
?>
