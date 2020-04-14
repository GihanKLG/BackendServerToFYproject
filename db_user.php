<?php
require_once 'db.php';
require_once 'db_institute.php';


function db_login($args) {
  $date = isset($args['date']) ? $args['date'] : date("Y-m-d");
  $args['date'] = $date;
  
  populate_user_id($user_list, 'user-pass', $args);
  populate_user_id($user_list, 'device', $args);

  if ($user_list) {
      
    if (!session_id()) session_start();
    $session_id = session_id();
    foreach ($user_list as $user_id => $user_data) {
      populate_user_session($user_id, $user_data);
      update_user_session_id($user_id, $session_id);

      if(isset($args['user']) && isset($args['pass']) && isset($args['device'])){
        if($args['device'] == "test123"){
          break;
        }else{
          update_user_device_id($user_id, $args);
        }
      }
    }
    succ_return(array('user_list' => array_values($user_list), 'session_id' => $session_id), 
      true, true, count($user_list));
  } else {
    fail_return(ERR_AUTHENTICATION);
  }
}

function populate_user_id(&$user_list, $login_type, $args) {
  $query = "";
  switch ($login_type) {
    case 'user-pass': 
      $user = 'anonymous'; $pass = '';
      if (isset($args['user'])) {
        $user = $args['user'];
        if (isset($args['pass'])) { $pass = $args['pass']; }
      }
      $query = "CALL get_user_by_login_pass('$user', '$pass')";
      break;

    case 'device':
      $device = '';
      if (isset($args['device'])) { $device = $args['device']; }
      $query = "CALL get_user_by_device('$device')"; 
      break;

    default: warn(__FILE__, __FUNCTION__, __LINE__, $login_type);
  }
  $user_dataset = array();
  foreach (db_execute($query) as $row) {
    $user_id = $row['user_id'];
    $dataset = array();
    foreach ($row as $key => $val) if (!is_null($val)) $user_list[$user_id][$key] = $val;
  }
}

function populate_user_session_list ($user_list) {
  foreach ($user_list as $user_id => $user_data) {
    populate_user_session($user_id, $user_data);
  }
}

function populate_user_session($user_id, $user_data) {
  if (isset($_SESSION['profile']['user_id'])) {
    array_push($_SESSION['profile']['user_id'], $user_id);
  } else { 
    $_SESSION['profile']['user_id'] = array($user_id);
  }

  if (isset($user_data['login_id'])) {
    if (isset($_SESSION['profile']['login_id'][$user_id])) {
      array_push($_SESSION['profile']['login_id'][$user_id], $user_data['login_id']);
    } else { 
      $_SESSION['profile']['login_id'][$user_id] = array($user_data['login_id']);
    }
  }

  if (isset($user_data['role'])) {
    if (isset($_SESSION['profile']['role_id'][$user_id])) {
      array_push($_SESSION['profile']['role_id'][$user_id], $user_data['role']);
    } else { 
      $_SESSION['profile']['role_id'][$user_id] = array($user_data['role']);
    }
  }
}

function add_device_id($user_id, $args){

  if(isset($args['device'])){
    $args['type'] = 3;
    $args['add_user_id'] = $user_id;
    $args['login_id'] = add_tbl_login($args); 
    $args['para'] = 3;
    $args['login_para_val'] = sha1($args['device']);
    debug(__FILE__,__FUNCTION__,__LINE__, $args['login_para_val']);
    add_tbl_login_para($args);
  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'add_device_id');
  }
}

function add_tbl_login_para($args){

  if(isset($args['login_id'])){
    $add_login_id = $args['login_id'];
    $add_login_para = $args['para'];
    $add_login_para_val = $args['login_para_val'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ?
    "Test Data - $my_user_id" : "";

    $query = " INSERT INTO `tbl_login_para` (
      `id`, `login`, `para`, `val`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_login_id, $add_login_para, '$add_login_para_val', NULL, NULL, $my_user_id, '$add_comment'
    )";

    db_execute($query);
  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'tbl_login_para');
  }
}

function add_tbl_login($args){

  if(isset($args['type'])){
    $add_user_id = $args['add_user_id'];
    $add_type_id = $args['type'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ?
    "Test Data - $my_user_id/$add_user_id" : "";

    $query = " INSERT INTO `tbl_login` (
      `id`, `user`, `type`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_user_id, $add_type_id, NULL, NULL, $my_user_id, '$add_comment'
    )";

    db_execute($query);

    $last_id = db_execute($query= "select max(id) as id from tbl_login");

    return $last_id['0']['id'];

  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'tbl_login');
  }
}

function check_user_add_permission($user_id, $add_role_id) {
  $query = "SELECT 'acl_user_role' AS acl_tbl, aur.id AS acl_id FROM acl_user_role aur, cfg_access ca 
    WHERE aur.access = ca.id AND LOWER(ca.name) IN ('create', 'all') AND aur.accessee = $user_id 
      AND aur.item = $add_role_id
    UNION 
    SELECT 'acl_role_role' AS acl_tbl, arr.id AS acl_id FROM acl_role_role arr, cfg_access ca, tbl_user_role tur
    WHERE arr.access = ca.id AND LOWER(ca.name) IN ('create', 'all') AND arr.accessee = tur.role 
      AND tur.id = $user_id AND arr.item = $add_role_id";
  return db_cached_execute($query);
}

function get_input_role_id(&$args) {
  $default_role_para_name = 'default_user_role_id';
  $role_id = DEFAULT_USER_ROLE_ID;

  if(isset($args['role_id'])) {
    $role_id = $args['role_id'];
  } else if (isset($_SESSION[$default_role_para_name])) {
    $role_id = $_SESSION[$default_role_para_name];
  } else {
    $query = "SELECT cs.id, cs.para, cs.val, cs.type AS type_id, LOWER(cdt.name) AS type_name 
      FROM cfg_system cs, cfg_data_type cdt WHERE cs.para = '$default_role_para_name' AND cs.type = cdt.id";
    $record_count = 0;
    $db_resp = db_cached_execute($query, $record_count);
    if ($record_count == 1) {
      $role_id = array_pop(array_column($db_resp, 'val'));
    }
  }
  $args['add_role_id'] = $role_id;

  return $role_id;
}

function get_user_by_id($user_id_list) {
  $query = "CALL get_user_by_id('" . implode(",", $user_id_list) . "')";
  debug(__FILE__,__FUNCTION__,__LINE__, $query);
  $user_list = array();
  foreach (db_execute($query) as $row) {
    $user_id = $row['user_id'];
    $dataset = array();
    foreach ($row as $key => $val) if (!is_null($val)) $user_list[$user_id][$key] = $val;
  };
  return $user_list;
}

?>
