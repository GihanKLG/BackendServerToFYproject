<?php
if (session_id() == '') {
    session_start();
}
$session_id = session_id();
require_once 'util.php';

// If session ID sent, let's validate it
// Or else we accept users without session ID if they are already logged in
if (isset($_REQUEST[FIELD_SESSION_ID]) || !is_existing_user_session()) {
  // User session data is not there
  $request_session_id = isset($_REQUEST[FIELD_SESSION_ID]) ? $_REQUEST[FIELD_SESSION_ID] : "";
  require_once 'db.php';
  if (!is_session_id_valid($request_session_id)) {
    //Session ID submitted in request is not valid
    clear_session();
    
    // Is this a login or user management request
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (!is_login_or_user_management($_REQUEST, $script_name)) {
      // It is not user login request
      $request_ip = $_SERVER['REMOTE_ADDR'];
      if (is_permitted_ip($request_ip, PERMITTED_IP_LIST)) {
        // Request is coming from a known IP
        $login = isset($_REQUEST[FIELD_LOGIN]) ? $_REQUEST[FIELD_LOGIN] : "anonymous";
        $pass = isset($_REQUEST[FIELD_PASS]) ? $_REQUEST[FIELD_PASS] : "";
        
        $validation = user_validation($login, $pass);
        if ($validation != OK_USER) {
          // Dialog Digital Reach do not support user/pass
          if (!is_permitted_ip($request_ip, NO_AUTH_IP_LIST)) {
            // Invalid access credentials
            warn(__FILE__, __FUNCTION__, __LINE__, $validation, $login, $pass, $request_ip, NO_AUTH_IP_LIST);
            exit(fail_return($validation, false));
          } else {
            info(__FILE__, __FUNCTION__, __LINE__, $login, $request_ip, NO_AUTH_IP_LIST);
            // Nothing to do; user allowed to continue using this IP
          }
        } else {
          // Nothing to do; let valid user to proceed
        }
      } else {
        // IP is not allowed to access
        warn(__FILE__, __FUNCTION__, __LINE__, ERR_AUTHENTICATION, $request_ip);
        exit(fail_return(ERR_AUTHENTICATION, false));
      }
    } else {
      // Nothing to do; login and user management activities do not need a valid session
    }
  } else {
    // Nothing to do; let the valid session to contine
  }
}

function is_permitted_ip($ip, $allowed_ip_list) {
  return in_array($ip, explode(",", $allowed_ip_list));
}

function clear_session() {
  if (session_id()) {
    if (isset($_SESSION[FIELD_USER_ID])) unset($_SESSION[FIELD_USER_ID]);
    if (isset($_SESSION[FIELD_ROLE])) unset($_SESSION[FIELD_ROLE]);
    session_destroy();
  }
}

function is_existing_user_session() {
  return isset($_SESSION[FIELD_USER_ID]) && $_SESSION[FIELD_USER_ID];
}

function is_session_id_valid ($id) {
  return $id && (check_session($id) == OK_USER);
}

function is_login_or_user_management ($request, $script) {
  $action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : ACTION_READ;
  return $action == ACTION_LOGIN || $action == ACTION_LOGOUT || 
    (substr_compare($script, "login.php", -9) == 0) || 
    (substr_compare($script, "user.php", -8) == 0 && $action == ACTION_ADD);
}
?>

