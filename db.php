<?php
require_once 'util.php';

$conn = $_SESSION['mysql_conn'] = mysqli_connect(DB_SERVER, DB_USER, DB_PASS);
if (mysqli_connect_errno()) {
  exit(db_error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_CONNECT, mysqli_errno($conn), mysqli_errno($conn)));
}

if (!mysqli_select_db($conn, DB_NAME)) {
  $query = "create database ".DB_NAME;
  if (!mysqli_query($conn, $query)) {
    exit(db_error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_CONNECT, mysqli_errno($conn), mysqli_errno($conn)));
  } else {
    info(__FILE__, __FUNCTION__, __LINE__, DB_NAME, "Created DB");
  }
}

function get_conn() {
  unset($_SESSION['mysql_conn']);
  if (!(isset($_SESSION['mysql_conn']) && $_SESSION['mysql_conn'])) {
    $_SESSION['mysql_conn'] = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
    $err_no = mysqli_connect_errno();
    if ($err_no) exit(db_error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_CONNECT, $err_no, $err_no));
  }
  return $_SESSION['mysql_conn']; 
}

function user_validation($login, $pass) {
  $encrypted_pass = pass_encrypt($pass);
  $login = strtolower($login);
  // Check permitted usernames
  $non_permitted_username_char = array('\'');
  if (str_replace($non_permitted_username_char, "", $login) != $login) {
    return ERR_NON_PERMITTED_USERNAME;
  }
  $query = "select ".FIELD_LOGIN.", ".FIELD_ID." from ".TBL_USER." 
            where ".FIELD_LOGIN."='$login' and ".FIELD_PASS."=sha1('$encrypted_pass')";
  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  
  $db_errno = mysqli_errno($conn);
  
  if (!$result && $db_errno) {
    info(__FILE__, __FUNCTION__, __LINE__, $result, $db_errno, $query);
    exit(db_error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, $db_errno, mysqli_errno($conn), false));
  } else if (!$result || mysqli_num_rows($result) != 1) {
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_AUTHENTICATION, $result, $query);
    return ERR_AUTHENTICATION;
  }
  $raw = mysqli_fetch_array($result, MYSQLI_ASSOC);
 
  $db_user_id = $raw[FIELD_ID];
  $db_login = $raw[FIELD_LOGIN];
  //$db_role = $raw[FIELD_ROLE];
  debug (__FILE__,__FUNCTION__,__LINE__,$db_user_id, $db_login);
  if ($db_login != $login) {
    info(__FILE__, __FUNCTION__, __LINE__, ERR_AUTHENTICATION, $login, $db_login);
    return ERR_AUTHENTICATION;
  }

  if (!session_id()) session_start();
  $_SESSION[FIELD_USER_ID] = $db_user_id;
  $_SESSION[FIELD_LOGIN] = $db_login;
  //$_SESSION[FIELD_ROLE] = $db_role;
  
  update_user_session_id($db_user_id, session_id());
  return OK_USER;
}

function user_login($login, $pass) {
  $validation = user_validation($login, $pass);
  if ($validation != OK_USER) {
    return fail_return($validation, true, true, false);
  }
  
  $session_id = session_id();
  succ_return_user(array($session_id), true, true, 1);
}

function db_error($file, $func, $line, $user_error, $db_errno, $sys_error, $echo = true, $json = true) {
  error($file, $func, $line, $user_error, $db_errno, $sys_error);
  return fail_return($user_error, $echo, $json);
}

function db_insert_on_duplicate_update($tbl, $field_list, $val_list, $echo = true, $json = true) {
  foreach ($val_list as $key => $val) {
    $val_list[$key] = addslashes($val);
  }

  $ordered_val = extract_val($field_list, $val_list);
  $duplicate_update_list = array();
  foreach($ordered_val as $key => $val) {
    if ($key == FIELD_CREATED_TS) continue;
    if (!isset($val) || strtolower($val) == 'null') continue;
    array_push($duplicate_update_list, $key."=".$val);
  }

  $insert_query = "insert into $tbl (".implode(", ", $field_list).") values (".implode(", ", $ordered_val).
                  ") ON DUPLICATE KEY UPDATE ".implode(", ", $duplicate_update_list).";";
  $conn = get_conn();
  $result = mysqli_query($conn, $insert_query); 
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno == 1146 && create_table($tbl, $field_list)) {
      return db_insert($tbl, $field_list, $val_list, $echo, $json);
    }else if ($db_errno == 1136) {
      $id_removed_field_list = array_remove(FIELD_ID, $field_list);
      warn(__FILE__, __FUNCTION__, __LINE__, $tbl, $id_removed_field_list, $val_list, $echo, $json);
      return db_insert($tbl, $id_removed_field_list, $val_list, $echo, $json);
    }else if ($db_errno == 1062) {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT_DUPLICATE, $tbl, $db_errno, $db_errno, $insert_query);
      return fail_return(ERR_DB_INSERT_DUPLICATE, $echo, $json);
    }else {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT, $tbl, $db_errno, $db_errno, $insert_query);
      return fail_return(ERR_DB_INSERT, $echo, $json);
    }
  }
  // info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_INSERT, $tbl, $field_list, $val_list, $insert_query);
  return succ_return(OK_DATA_INSERT, $echo, $json);
}

function db_insert($tbl, $field_list, $val_list, $echo = true, $json = true) {


  foreach ($val_list as $key => $val) {
    $val_list[$key] = addslashes($val);
  }
  $ordered_val = extract_val($field_list, $val_list);
  $insert_query = "insert into $tbl (".implode(", ", $field_list).") values (".implode(", ", $ordered_val).");";
  $conn = get_conn();
  debug(__FILE__, __FUNCTION__, __LINE__, $insert_query);
  $result = mysqli_query($conn, $insert_query);
  
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno == 1146 && create_table($tbl, $field_list)) {
      return db_insert($tbl, $field_list, $val_list, $echo, $json);
    } else if ($db_errno == 1136) {
      $id_removed_field_list = array_remove(FIELD_ID, $field_list);
      warn(__FILE__, __FUNCTION__, __LINE__, $tbl, $id_removed_field_list, $val_list, $echo, $json);
      return db_insert($tbl, $id_removed_field_list, $val_list, $echo, $json);
    } else if ($db_errno == 1062) {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT_DUPLICATE, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return fail_return(ERR_DB_INSERT_DUPLICATE, $echo, $json);
    } else {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return fail_return(ERR_DB_INSERT, $echo, $json);
    }
  }
  //if($val_list['type']!='Add_Massage')
  //update_activity_status($val_list);
  // info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_INSERT, $tbl, $field_list, $val_list, $insert_query);
  return succ_return(OK_DATA_INSERT, $echo, $json);
}

function db_multi_insert($tbl, $field_list, $val_list_of_list, $activity, $echo = true, $json = true) {
  $field_list = array_diff($field_list, array(FIELD_ID));
  if(count($val_list_of_list) == 0) {
    info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_INSERT, $tbl, $field_list, $val_list_of_list);
    return succ_return(OK_DATA_INSERT, $echo, $json);
  }
	/* INSERTINTO`test`.`tbl_schedule_instance` (`id`, `summary`, `doctor`, `date`, `time`, `patient`, `status`) VALUES 
	 * (NULL ,'4','21','2012-07-11','10:30','10', 0), (NULL ,'6','20','2012-07-19','12:20','25', 0) 
	 */
  $insert_query = "insert into $tbl (".implode(", ", $field_list).") values ";
  $insert_arr = array();
  foreach ($val_list_of_list as $val_list) {
    $ordered_val = extract_val($field_list, $val_list);
    array_push($insert_arr, "(".implode(", ", $ordered_val).")");
  }
  $insert_query .= implode(", ", $insert_arr);
  
  $conn = get_conn();
  $result = mysqli_query($conn, $insert_query);
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno == 1162) {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT_DUPLICATE, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return fail_return(ERR_DB_INSERT_DUPLICATE, $echo, $json);
    } else {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return fail_return(ERR_DB_INSERT, $echo, $json);
    }
  }
  update_activity_status($activity);
  info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_INSERT, $tbl, $field_list, $insert_query);
  return succ_return(OK_DATA_INSERT, $echo, $json);
}

function extract_val($field_list, $val_list) {
  $ret = array();
  foreach ($field_list as $field) {
    if (isset($val_list[$field])) {
      $val = $val_list[$field];
      if ($field == FIELD_USER_ID) {
        $val = (isset($val) && $val) ? $val : 'null';
      } else if (!preg_match("/^\(.*\)$/", $val)) {
        $val = "'$val'";
      }
      $ret[$field] = $val;
    } else {
      $ret[$field] = 'null';
    }
  }
  return $ret;
}

function create_table($tbl, $field_list) {
  $query = "show table $tbl";

  $conn = get_conn();
  if(mysqli_query($conn, $query)) {
    return false;
  }

  $field_list = gen_insert_field_list($field_list);
  $query = "create table $tbl (".implode(", ", $field_list).")";
  if(mysqli_query($conn, $query)) {
    info(__FILE__, __FUNCTION__, __LINE__, "create", $tbl, $field_list);
    return true;
  }
  return false;
}

function gen_insert_field_list($field_list) {
  $ret = array();
  foreach ($field_list as $field) {
    switch ($field) {
      case FIELD_ID:
        array_push($ret, "$field int auto_increment primary key");
        break;
      default:
        array_push($ret, "$field varchar(50)");
        break;
    }
  }
  return $ret;
}

function db_delete($tbl, $field_list, $controls, $echo = true, $json = true) {
  $where_clause = gen_where_clause($tbl, $field_list, $controls);
  if(!$where_clause) {
    $ret = false;
    foreach ($controls as $subset) {
      if (is_array($subset)) {
        $ret = true;
        db_delete($tbl, $field_list, $subset, false, false);
      }
    }
    return ($ret) ? succ_return(OK_DATA_DELETE, $echo, $json) : fail_return(ERR_DB_DELETE_NO_CONDITION, $echo, $json);
  }
  $query = "delete from `$tbl` $where_clause;";
  
  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_DELETE, $tbl, $db_errno, mysqli_errno($conn), $query);
    return fail_return(ERR_DB_DELETE, $echo, $json);
  }
  $total = mysqli_affected_rows($conn);
  info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_DELETE, $tbl, $field_list, $controls, $total, $query);
  return succ_return(OK_DATA_DELETE, $echo, $json, $total);
}

function db_read($tbl, $controls = array(), $echo = true, $json = true) {
  get_tbl_desc($tbl);
  return db_search($tbl, get_field_list($tbl), $controls, $echo, $json);
}

function db_search($tbl, $field_list, $controls, $echo = true, $json = true, &$total = 0) {
  foreach ($controls as $key => $val) {
    $controls[$key] = addslashes($val);
  }
  $where_clause = gen_where_clause($tbl, array_keys($controls), $controls);
  $order_by = gen_order_by($tbl, $controls);
  $query = "select SQL_CALC_FOUND_ROWS `$tbl`.`".implode("`, `$tbl`.`", $field_list)."` from $tbl $where_clause $order_by ".gen_limit_clause($controls);
  debug(__FILE__,__FUNCTION__,__LINE__, $query);
  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  
  $total = get_total_rows($conn);
  $ret = false;
  
  if (!$result || !mysqli_num_rows($result)) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno) {
      error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, db_errno, mysqli_errno($conn), $query);
      $ret = fail_return(ERR_DB_READ, $echo, $json);
    } else {
      warn(__FILE__, __FUNCTION__, __LINE__, OK_DATA_EMPTY_SET, $query);
      $ret = succ_return(array(), $echo, $json, $total);
    }
  } else {
    $data = array();
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
      if ($tbl == TBL_USER) {
        $row = populate_user_para($row);
      }
      // Remove passwords from return results
      unset($row[FIELD_PASS]);
      foreach ($row as $key => $val) {
        $row[$key] = utf8_encode($val);
      }
      array_push($data, $row);
    }
    mysqli_free_result($result);
    $ret = succ_return($data, $echo, $json, $total);
  }
  return $ret;
}

function db_update($tbl, $field_list, $val_list, $echo = true, $json = true, $control_list=array()) {
  foreach ($val_list as $key => $val) {
    $val_list[$key] = addslashes($val);
  }
  foreach ($control_list as $key => $val) {
    $control_list[$key] = addslashes($val);
  }
  if (!$control_list && isset($val_list[FIELD_ID])) $control_list = array(FIELD_ID => $val_list[FIELD_ID]);
  
  $where_clause = gen_where_clause($tbl, $field_list, $control_list);
  $set_clause = gen_set_clause($tbl, get_field_list($tbl), $val_list);
  
  if(!$set_clause) {
    return fail_return(ERR_DB_UPDATE_NO_DATA, $echo, $json);
  }
  $query = "update `$tbl` $set_clause $where_clause";

  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_UPDATE, $tbl, $db_errno, mysqli_errno($conn), $query);
    return fail_return(ERR_DB_UPDATE, $echo, $json);
  }
  $total = mysqli_affected_rows($conn);
  return succ_return(OK_DATA_UPDATE, $echo, $json, $total);
}

function db_query($query, $echo = false, $json = false) {
  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  $total = get_total_rows($conn);
  $ret = false;
  
  if (!$result || !mysqli_num_rows($result)) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno) {
      error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, db_errno, mysqli_errno($conn), $query);
      $ret = fail_return(ERR_DB_READ, $echo, $json);
    } else {
      $ret = succ_return(array(), $echo, $json, $total);
    }
  } else {
    $data = array();
    while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
      // Remove passwords from return results
      unset($row[FIELD_PASS]);
      foreach ($row as $key => $val) {
        $row[$key] = utf8_encode($val);
      }
      array_push($data, $row);
    }
    mysqli_free_result($result);
    $ret = succ_return($data, $echo, $json, $total);
  }
  return succ_return($ret, $echo, $json, $total);;
}

function gen_update_list($tbl, $field_list, $val_list) {
  $tbl_desc = get_tbl_desc($tbl);
  $update_list = array();
  foreach ($field_list as $field) {
    if (isset($val_list[$field])) {
      if ($tbl_desc[$field] == FIELD_TYPE_STR) {
        array_push($update_list, "`$field` = '".$val_list[$field]."'");
      } elseif ($tbl_desc[$field] == FIELD_TYPE_INT) {
        array_push($update_list, "`$field` = ".$val_list[$field]."");
      } elseif ($tbl_desc[$field] == FIELD_TYPE_OTHER) {
        array_push($update_list, "`$field` = '".$val_list[$field]."'");
      } else {
        array_push($update_list, "`$field` = '".$val_list[$field]."'");
      }
    }
  }
  return $update_list;
}

function gen_limit_clause($controls) {
  $start = (isset($controls['start']) && $controls['start']) ? $controls['start'] : 0;
  $limit = (isset($controls['limit']) && $controls['limit']) ? $controls['limit'] : DEFAULT_PAGE_SIZE;
  return " limit $start, $limit";
}

function gen_where_clause($tbl, $field_list, $val_list) {
  $ret = "";
  $tbl_desc = get_tbl_desc($tbl);
  $condition_list = array();
  $extra_tbl_list = array();
  foreach ($val_list as $field => $val) {
    $tok = explode(".", $field);
    if (count($tok) == 2 && !(isset($val_list[FIELD_ID]) && $val_list[FIELD_ID])) {
      $other_desc = get_tbl_desc($tok[0]);
      $tbl_desc[$field] = $other_desc[$tok[1]];
      if (!in_array($tok[0], $extra_tbl_list)) array_push($extra_tbl_list, $tok[0]);
    }
  }
  foreach ($field_list as $field) {
    $tok = explode(".", $field);
    $full_field = (count($tok) == 2) ? "`".$tok[0]."`.`".$tok[1]."`" : "`$tbl`.`$field`";
    if (isset($val_list[$field]) && $val_list[$field] != "" && isset($tbl_desc[$field]) && $tbl_desc[$field]) {
      if (isset($tbl_desc[$field]) && $tbl_desc[$field] == FIELD_TYPE_STR && !(isset($exact_match_field[$field]) && $exact_match_field[$field])) {
        array_push($condition_list, "$full_field like '%".$val_list[$field]."%'");
      } elseif ($tbl_desc[$field] == FIELD_TYPE_INT) {
        array_push($condition_list, "$full_field = ".$val_list[$field]);
      } elseif ($tbl_desc[$field] == FIELD_TYPE_OTHER) {
        array_push($condition_list, "$full_field = '".$val_list[$field]."'");
      } else {
        array_push($condition_list, "$full_field = '".$val_list[$field]."'");
      }
    }
    if($field == FIELD_TIMESTAMP) {
      if (isset($val_list[FIELD_TIMESTAMP_MIN]) && $val_list[FIELD_TIMESTAMP_MIN]) {
        array_push($condition_list, "`$tbl`.`$field`>='".$val_list[FIELD_TIMESTAMP_MIN]."'");
      }
      if (isset($val_list[FIELD_TIMESTAMP_MAX]) && $val_list[FIELD_TIMESTAMP_MAX]) {
        array_push($condition_list, "`$tbl`.`$field`<='".$val_list[FIELD_TIMESTAMP_MAX]."'");
      }
    }
  }
  
  if($condition_list) {
    $ret = "where ".implode(" and ", $condition_list);
    foreach ($extra_tbl_list as $extra_tbl) {
      $ret = ", $extra_tbl ".$ret." and ".$tbl_join_map[$tbl][$extra_tbl];
    }
  }
  return $ret;
}

function gen_order_by($tbl, $controls) {
  if(isset($controls[FIELD_ORDER_BY]) && $controls[FIELD_ORDER_BY]) {
    return "ORDER BY ".$controls[FIELD_ORDER_BY];
  } else {
    return "";
  }
}

function gen_set_clause($tbl, $field_list, $val_list) {
  $ret = "";
  $update_list = gen_update_list($tbl, $field_list, $val_list);
  if($update_list) {
    $ret = "set ".implode(", ", $update_list);
  }
  return $ret;
}

function get_tbl_desc($tbl) {
  $ret = array();
  if(isset($_SESSION['tbl_desc'][$tbl])) {
    $ret = $_SESSION['tbl_desc'][$tbl];
  } else {
    $query = "desc $tbl";

    $conn = get_conn();
    $result = mysqli_query($conn, $query);
    if (!$result || !mysqli_num_rows($result)) {
      $db_errno = mysqli_errno($conn);
      if ($db_errno) {
        error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, $db_errno, mysqli_errno($conn), $query);
      } else {
        warn(__FILE__, __FUNCTION__, __LINE__, OK_DATA_EMPTY_SET, $query);
      }
    } else {
      while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        $type = FIELD_TYPE_OTHER;
        if (substr($row['Type'], 0, 3) == 'int') {
          $type = FIELD_TYPE_INT;
        } elseif (substr($row['Type'], 0, 4) == 'char') {
          $type = FIELD_TYPE_STR;
        } elseif (substr($row['Type'], 0, 7) == 'tinyint') {
          $type = FIELD_TYPE_INT;
        } elseif (substr($row['Type'], 0, 7) == 'varchar') {
          $type = FIELD_TYPE_STR;
        }
        $ret[$row['Field']] = $type;
      }
      mysqli_free_result($result);
      $_SESSION['tbl_desc'][$tbl] = $ret;
      info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_READ, $tbl);
    }
  }
  return $ret;
}

function get_row_count($tbl) {
  $ret = 0;
  $query = "select count(*) from $tbl";

  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  if (!$result || mysqli_num_rows($result) != 1) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno) {
      error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, db_errno, mysqli_errno($conn), $query);
    } else {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INCORRECT_ROW_COUNT, $query);
    }
  } else {
    $row = mysqli_fetch_array($result, MYSQL_NUM);
    mysqli_free_result($result);
    $ret = $row[0];
    info(__FILE__, __FUNCTION__, __LINE__, OK_DATA_READ, $tbl, $ret);
  }
  return $ret;
}

function get_total_rows($conn) {
  $result = mysqli_query($conn, "SELECT FOUND_ROWS()");
  $found_rows = 0;
  if($result) $found_rows = mysqli_fetch_row($result);
  return $found_rows[0];
}

function update_user_session_id($db_id, $session_id) {

  $query = "SELECT id from tbl_login where user = $db_id";
  $result = db_execute($query);
  $result = array_shift($result);
  $login = $result['id'];

  $query = "SELECT id from tbl_session where login = $login";
  $result = db_execute($query);

  debug(__FILE__,__FUNCTION__,__LINE__, $query);

  if(!empty($result)){  
    $query = " UPDATE tbl_session set session_id ='$session_id' where login = '$login' ";
    debug(__FILE__,__FUNCTION__,__LINE__, $query);
    $conn = get_conn();
    $result = mysqli_query($conn, $query);
    if (!$result) {
      $db_errno = mysqli_errno($conn);
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_UPDATE, $db_errno, mysqli_errno($conn), $query);
    } 
  }else{
    add_session_id($login, $session_id);
  }
}

function add_session_id($login_id, $session_id){

  $query = "INSERT INTO `tbl_session` (`id`, `login`, `session_id`, `created_ts`, `updated_ts`, `updated_by`, `comment`) 
  VALUES (NULL, '$login_id', '$session_id',current_timestamp(), current_timestamp(), '$login_id', '')";

  $result = db_execute($query);
}

function check_device_id($user_id){

  debug(__FILE__,__FUNCTION__,__LINE__, $user_id);

  $query = "SELECT id from tbl_login where user = $user_id and type = 3";
  debug(__FILE__,__FUNCTION__,__LINE__, $query);
  $result = db_execute($query);
  debug(__FILE__,__FUNCTION__,__LINE__, $result);
  return $result;
}

function update_user_device_id($user_id, $args){

  $query = "SELECT id from tbl_login where user = $user_id and type = 3";
  $result = db_execute($query);

  debug(__FILE__,__FUNCTION__,__LINE__, $result);
  if(!empty($result)){
  $login_id = $result['0']['id'];
  $deivce_id = $args['device'];

  $query = "UPDATE tbl_login_para set val =sha1('$deivce_id') where login = '$login_id'";
  $conn = get_conn();
  $result = mysqli_query($conn, $query);
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_UPDATE, $db_errno, mysqli_errno($conn), $query);
  }
  }else{
    add_device_id($user_id, $args);
  }
}

function check_session($session_id) {
 // $query = "select ".FIELD_LOGIN.", ".FIELD_ID." from ".TBL_USER." where ".FIELD_SESSION_ID."='$session_id'";
  
//   $query = "SELECT 
//       tu.name AS user_name, tu.id AS user_id, tl.id AS login_id, qlp.id AS para_id, 
//       qlp.val AS login, tr.name AS role
//     FROM tbl_user tu 
//       INNER JOIN tbl_login tl ON tu.id = tl.user
//       INNER JOIN tbl_session ts ON tl.id = ts.login AND ts.session_id = '$session_id'
//       LEFT OUTER JOIN (
//         SELECT tlp.login, tlp.id, tlp.val FROM tbl_login_para tlp, cfg_login_para clp 
//         WHERE tlp.para = clp.id AND LOWER(clp.name) = 'name'
//       ) qlp ON qlp.login = tl.id 
//       LEFT OUTER JOIN tbl_user_role tur ON tur.user = tl.id 
//       LEFT OUTER JOIN tbl_role tr ON tr.id = tur.role";
  
//   $conn = get_conn();
//   $result = mysqli_query($conn, $query);
  
//   $db_errno = mysqli_errno($conn);
//   if (!$result && $db_errno) {
//     exit(db_error(__FILE__, __FUNCTION__, __LINE__, ERR_DB_READ, $db_errno, mysqli_errno($conn), false));
//   }
//   if (!$result || !mysqli_num_rows($result)) {
//     info(__FILE__, __FUNCTION__, __LINE__, ERR_AUTHENTICATION, $result);
//     return ERR_AUTHENTICATION;
//   }
//   $raw = mysqli_fetch_array($result, MYSQLI_ASSOC);
  
//   $db_user_id = $raw[FIELD_USER_ID];
//   $db_login = $raw[FIELD_LOGIN];
//  // $db_role = $raw[FIELD_ROLE];
  
//   $_SESSION[FIELD_USER_ID] = $db_user_id;
//   $_SESSION[FIELD_LOGIN] = $db_login;
//  // $_SESSION[FIELD_ROLE] = $db_role;
  
//   $login = $_SESSION[FIELD_LOGIN];
//   if ($db_login != $login) {
//     info(__FILE__, __FUNCTION__, __LINE__, ERR_AUTHENTICATION, $login, $db_login);
//     return ERR_AUTHENTICATION;
//   }
  return OK_USER; 
}

function db_get_last_insert_id() {
  return mysql_insert_id();
}

function get_read_permission_list() {
  return array(2, 5, 8, 11);
}

function db_cached_execute ($query, &$total = 0, $forced = false) {
  if (!isset($_SESSION[$query]) || !$forced) {
    $_SESSION[$query] = db_execute($query);
  }
  return $_SESSION[$query];
}

function db_execute($query, &$total = 0) {
  $conn = get_conn();
  mysqli_next_result($conn);
  $result = mysqli_query($conn, $query);

  $ret = array();
  if ($result === true) $ret = $conn->insert_id;
  $total = get_total_rows($conn);
  
  $db_errno = mysqli_errno($conn);
  if (!$result && $db_errno) {
    warn(__FILE__, __FUNCTION__, __LINE__, $query, $result, $db_errno);
  }

  if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) array_push($ret, $row);
  }

  return $ret;
}

function updated_by($args) {
  $session_id = session_id();
  if (isset($args['session_id']) && $args['session_id']) {
    $session_id = $args['session_id'];
  }
  $query = "
    select tbl_user.id 
    from tbl_user
    inner join tbl_session on tbl_session.login = tbl_user.id
    where tbl_session.session_id = '$session_id'
  ";
  $result = db_execute($query);
  
  if (!$result || count($result) != 1) {
    error(__FILE__, __FUNCTION__, __LINE__, $result);
    return DEFAULT_UPDATED_BY;
  }
  $raw = array_pop($result);
  return $raw[FIELD_ID];
}

function check_session_id($args) {
  $session_id=$args['session_id'];

  $query= "
  SELECT tbl_session.id 
  FROM tbl_session
  INNER JOIN tbl_login ON tbl_session.login = tbl_login.id 
  INNER JOIN tbl_user ON tbl_login.user = tbl_user.id
  WHERE tbl_session.session_id = '$session_id'
  ";

  $result= db_execute($query);
  
  if (empty($result)) {
    return false;
  } else {
    return true;
  }
}


function db_activity_insert($tbl, $field_list, $val_list, $echo = true, $json = true) {


  foreach ($val_list as $key => $val) {
    $val_list[$key] = addslashes($val);
  }
  $ordered_val = extract_val($field_list, $val_list);
  $insert_query = "insert into $tbl (".implode(", ", $field_list).") values (".implode(", ", $ordered_val).");";
  $conn = get_conn();
  $result = mysqli_query($conn, $insert_query);
  
  if (!$result) {
    $db_errno = mysqli_errno($conn);
    if ($db_errno == 1146 && create_table($tbl, $field_list)) {
      return false;
      //return db_insert($tbl, $field_list, $val_list, $echo, $json);
    } else if ($db_errno == 1136) {
      $id_removed_field_list = array_remove(FIELD_ID, $field_list);
      warn(__FILE__, __FUNCTION__, __LINE__, $tbl, $id_removed_field_list, $val_list, $echo, $json);
      return false;
      //return db_insert($tbl, $id_removed_field_list, $val_list, $echo, $json);
    } else if ($db_errno == 1062) {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT_DUPLICATE, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return false;
      //return fail_return(ERR_DB_INSERT_DUPLICATE, $echo, $json);
    } else {
      warn(__FILE__, __FUNCTION__, __LINE__, ERR_DB_INSERT, $tbl, $db_errno, mysqli_errno($conn), $insert_query);
      return false;
      //return fail_return(ERR_DB_INSERT, $echo, $json);
    }
  }

  return true;
}


function update_activity_status($activity_id, $echo = true, $json = true) {
  $user_id = $_SESSION['user_id'];
  $date = date('Y-m-d H:i:s');
  $query = "SELECT id FROM cfg_activity_status WHERE name = 'Done'";
  $result = db_execute($query);
  $result = array_shift($result);
  $status = $result['id'];
  $query ="UPDATE tbl_schedule_activity 
    SET status = '$status', updated_ts = '$date', updated_by = '$user_id' 
    WHERE id='$activity_id'";
  debug(__FILE__,__FUNCTION__,__LINE__, $query);
  $result = db_execute($query);
  $error = ERR_SUCCESS_ACTIVITY;
  exit(succ_return($error, false));
}

?>