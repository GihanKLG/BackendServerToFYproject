<?php
header("Access-Control-Allow-Origin: *");
require_once 'definition.php';
define('LOG_FILE', AUDIT_LOG_DIR.'/'.AUDIT_LOG_FILE_PREFIX.date("Ymd").AUDIT_LOG_FILE_SUFFIX.'.'.AUDIT_LOG_FILE_EXT);

function debug() {
  $args = func_get_args();
  write(array_merge(array('DEBUG'), $args));
}

function info() {
  $args = func_get_args();
  write(array_merge(array('INFO'), $args));
}

function warn() {
  $args = func_get_args();
  write(array_merge(array('WARN'), $args));
}

function error() {
  $args = func_get_args();
  write(array_merge(array('ERROR'), $args));
}

function todo() {
  $args = func_get_args();
  write(array_merge(array('TODO'), $args));
}

function write() {
  $args = func_get_args();
  $i = 0;
  if (sizeof($args) == 1 && is_array($args)) {
    $args = $args[0];
  }
  $print_arr = array();
  foreach ($args as $arg) {
    array_push($print_arr, arg_to_str($arg));
  }
  
  $t = microtime(true);
  $micro = sprintf("%06d",($t - floor($t)) * 1000000);
  $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $t) );

  write_log($d->format("H:i:s.u").", ".$_SERVER['REMOTE_ADDR'].", ".session_id().", ".implode(", ", $print_arr)."\r\n", 3, LOG_FILE);
}

function write_log($log, $type, $dest) {
  error_log($log, $type, $dest);
}

function arg_to_str($arg) {
  $str = "";
  if (is_array($arg)) {
    $str .="array(";
    $str_arr = array();
    foreach ($arg as $key => $val) {
      array_push($str_arr, "$key => ".arg_to_str($val));
    }
    $str .= implode(", ", $str_arr);
    $str .= ")";
  } else if (is_object($arg)) {
    $str .= "object(".var_export($arg, true).")";
  } else if (is_resource($arg)) {
    $str .= "resource(".var_export($arg, true).")";
  } else if (is_file($arg)) {
    $str .= basename($arg, ".php");
  } else if (is_bool($arg)) {
    $str .= ($arg) ? 'true' : 'false';
  } else {
    $str .= $arg;
  }
  return $str;
}

function json_return($resp, $echo = false) {
  header('Content-Type: application/json');
  $return = json_encode(($resp));
  if($echo) {
    echo $return;
  }
  list($usec, $sec) = explode(" ", microtime());
  $time =  date("Y-m-d H:i:s:",$sec).intval(round($usec*1000));
  info(__FILE__, __FUNCTION__, __LINE__, $time);
  return $return;
}

function fail_return($details, $echo = true, $json = true, $success = true) {
  if ($json) {
    return json_return(array(JSON_SUCCESS => $success, JSON_STATUS => false, JSON_DETAILS => $details), $echo);
  } else {
    if($echo) {
      info(__FILE__, __FUNCTION__, __LINE__, $details);
      echo arg_to_str($details);
    }
      return $details;
  }
}

function succ_return($details, $echo = true, $json = true, $total = 0) {
  
  if ($json) {
    return json_return(array(JSON_SUCCESS => true, JSON_STATUS => true, JSON_DETAILS => $details, JSON_TOTAL => $total), $echo);
  } else {
    if($echo) {
      info(__FILE__, __FUNCTION__, __LINE__, $details);
      echo arg_to_str($details);
    }
    return $details;
  }
}

function succ_return_user($session_id, $echo = true, $json = true, $total = 0) {

  $s_id = implode('', $session_id);

  if ($json) {
    $id = get_user_id($s_id);
    $user_details =  user_info($s_id);
    $own = get_own_alert($s_id);
    $other = get_other_alert($s_id);
    //$meal_count = get_schedule('meal');
    $num_adult = get_user_count(7); //input role id 
    $num_institute = get_num_institute($s_id);
    $num_caregiver  = get_user_count(1);
    $num_activity = get_num_activity();

    $post_data = array(
    'date' =>  date("d F Y "),
    'session_id' => $session_id,
    'profile' => [
      'id' => $id['0']['id'],
      'image' => '',
      'role' => array($user_details['0']['role'],$user_details['0']['user_name'],$user_details['0']['user_role']),
    ],
    'alert' => [
      'own' => $own['0']['count'], //getting user notification count 
      'other' => $other['0']['count'], //getting owner's other notification count
    ],
    'Meal' => [
      //'count' => $meal_count['0']['count'],
      'done' => '',
    ],
    'medicine' => [
      'count' => '',
      'done' => '',
    ],
    'vital' => [
      'count' => '',
      'done' => '',
    ],
    'communication' => [
      'chat' => '',
      'message' => '',
    ],
    'count' => [
      'adult' => $num_adult['0']['count'],
      'institute' => $num_institute['0']['count'],
      'caregiver' => $num_caregiver['0']['count'],
      'activity' => $num_activity['0']['count'],
    ],
    'timestamp' => date('H:i:sa'),
    );

    $post_data = json_encode($post_data);

    return json_return(array(JSON_SUCCESS => true, JSON_STATUS => true, JSON_DETAILS => json_decode($post_data) , 'notification' => $details1, JSON_TOTAL => $total), $echo);
  } else {
    if($echo) echo arg_to_str($details);
    return $details;
  }
}

function is_set_all($array, $field) {
  if (!is_array($field)) {
      return isset($array[$field]);
  }
  return true;
}

function is_set_any($array, $field) {
  if (!is_array($field)) {
    return isset($array[$field]);
  }
  foreach ($field as $key) {
    if(isset($array[$key])) return true;
  }
  return false;
}

function array_remove($val, $arr, $preserve = true) {
  if (empty($arr) || !is_array($arr)) { return $arr; }

  foreach(array_keys($arr,$val) as $key){ unset($arr[$key]); }

  return ($preserve) ? $arr : array_values($arr);
}

function create_table_from_list($list) {
  if (!$list) return "";
  $out = "";
  $out .= "<table border='1'>\n";
  $head = array_shift($list);
  $out .= "<tr>";
  if (is_array($head)) {
    foreach ($head as $field) {
      $out .= "<th>$field</th>";
    }
  } else {
    $out .= "<th>$head</th>";
  }
  $out .= "</tr>\n";
  foreach ($list as $row) {
    $out .= "<tr>";
    if (is_array($row)) {
      foreach ($row as $field) {
        $out .= "<td>$field</td>";
      }
    } else {
      $out .= "<td>$row</td>";
    }
    $out .= "</tr>\n";
  }
  $out .= "</table>\n";
  return $out;
}

function tbl_array_from_associate_array($arr) {
  return array_merge(array_keys($arr), array_values($arr));
}

function get_request($request, $post_str) {
  $ret = is_array($request) ? $request : json_decode($request);
  if ($post_str) {
    $decoded = json_decode($post_str, true);
    if(!$decoded) {
      $pairs = explode("&", $post_str);
      $vars = array();
      foreach ($pairs as $pair) {
        $nv = explode("=", $pair);
        $name = urldecode($nv[0]);
        $value = urldecode($nv[1]);
        $decoded[$name] = $value;
      }
    }
    switch (gettype($decoded)) {
      case "NULL":
        break;
      case "array":
        $ret = array_merge($ret, $decoded);
        break;
      default:
        $ret = array_merge($ret, (array) $decoded);
        break;
    }
  }
  return unique_sort($ret);
}

function get_field_list($module) {
  $module = (is_file($module)) ? basename($module, ".php") : $module;
  
  if ((!isset($_SESSION['field_list'][$module]))||(isset($_SESSION['field_list'][$module])) ) {
    switch ($module) {
      case 'activity':
      case 'db_activity':
      case 'tbl_activity':
      $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_PARENT, FIELD_TYPE, FIELD_ASSIGNEE, 
      FIELD_ASSIGNEE_TYPE, FIELD_ABOUT, FIELD_ABOUT_TYPE, FIELD_STATUS,
      FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
      break;

      case 'tbl_activity_info':
      case 'util_activity':
      $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_ACTIVITY, FIELD_PARA, FIELD_VALUE,
        FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
      break;

      case 'access':
      case 'user':
      case 'db_user':
      case 'tbl_user':
      case 'util_user':
      case 'reset_pass':
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_NAME,FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break;

      case 'vital':
      case 'tbl_vitals':
      case 'util_vital':
      $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_PATIENT_ID, FIELD_VITAL, FIELD_VALUE, 
        FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
      break;  
      case 'maintenance': 
      case 'util_maintenance':
      case 'tbl_maintenance':
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_MAINTENANCE_CODE, FIELD_MAINTENANCE_STATUS, FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break;

      case 'notification':
      case 'db_notification':
      case 'util_notification':
      case 'tbl_notification':
        $_SESSION['field_list'][$module] = array(
          FIELD_ID, FIELD_USER, FIELD_ACTIVITY, FIELD_MESSAGE, FIELD_STATUS, 
          FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break;

      case 'doctor': 
      case 'util_doctor':
      case 'tbl_prescription':
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_PRESCRIBED_TO, FIELD_PRESCRIBED_BY, FIELD_MEDICINE, FIELD_ROUTE, FIELD_DOSAGE, 
        FIELD_DOSAGE_UNIT, FIELD_TIMING, FIELD_DURATION, FIELD_DURATION_UNIT, FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break; 

      case 'tbl_user_inventory_tx':  
      case 'institute_inventory_tx':
      case 'util_medicine': 
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_ITEM, FIELD_QUANTITY, FIELD_USER, FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break;

      case 'db_medicine':
      case 'tbl_activity':
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_SCHEDULE, FIELD_TYPE, FIELD_ASSIGNEE, FIELD_ASSIGNEE_TYPE, FIELD_ABOUT, FIELD_ABOUT_TYPE, FIELD_STATUS, FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
        break;       

      case 'institute':
      case 'db_institute':
      case 'tbl_institute':
        $_SESSION['field_list'][$module] = array(FIELD_ID, FIELD_PARENT, FIELD_TYPE, FIELD_NAME, FIELD_CREATED_TS, FIELD_UPDATED_TS, FIELD_UPDATED_BY, FIELD_COMMENT);
    }
  }
  return $_SESSION['field_list'][$module];  
}

function unique_sort($arr) {
  $ret = array();
  foreach ($arr as $key => $val) {
    if (!array_key_exists($key, $ret)) {
      $ret[$key] = $val;
    }
  }
  asort($ret);
  return $ret;
} 

function pass_encrypt($pass) {
  //return base64_encode($pass);
  return $pass;
}

function remove_fields ($key_val_list, $key_list_to_remove) {
  foreach ($key_list_to_remove as $key_to_remove) {
    unset($key_val_list[$key_to_remove]);
  }
  return $key_val_list;
}

function remove_tbl_fields ($key_val_list, $key_list_to_remove) {
  foreach ($key_list_to_remove as $key_to_remove) {
      foreach($key_val_list as $key=>$key_val){
          if($key_val == $key_to_remove){
              unset($key_val_list[$key]);
          }
      }
  }
  return $key_val_list;
}

function format_phone_num ($str_num) {
  // let's remove all spaces, dashes and brackets in the given number (E.g. 077 378 5550, 077-378-5550)
  $str_num = preg_replace("/[\s-\(\)]+/", "", $str_num);
  // If the number is 0773785550, +94773785550 or 0094773785550 format let's remove leading zero & +
  $str_num = ltrim($str_num, "0+");
  // Let's add 94 in front if number length is 9 (E.g 773785550)
  $str_num = (strlen($str_num) == 9) ? "94".$str_num : $str_num;
  // Return formatted number
  return $str_num;
}

function format_vehicle_num ($vehicle_num) {
  // Remove spaces from two ends and replace multiple spaces and dashes with a single space
  $vehicle_num = strtoupper(preg_replace("/[\s-]+/", " ", trim($vehicle_num)));
  // Return formatted number
  return $vehicle_num;
}

function process_request() {
  $postdata = file_get_contents("php://input");
  $_REQUEST = get_request($_REQUEST, $postdata);
}

function get_action() {
  $action = ACTION_READ;
  if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
  } else if (isset($_SESSION['action'])) {
    $action = $_SESSION['action'];
  }
  return $action;
}

function process_request_and_get_action() {
  process_request();
  return get_action();
}

function reset_pass($request) {
  unset($request[FIELD_PASS]);
  unset($request[FIELD_PASS1]);
  unset($request[FIELD_PASS2]);
  return $request;
}

function populate_dataset(
  &$populating_list, $populating_key, $element, $dataset, 
  $is_list = true, $remove_populating_id = true, $remove_null = true
) {

  debug(__FILE__,__FUNCTION__,__LINE__, $element, $dataset);
  if (is_array($dataset)) {
    foreach ($dataset as $row) {
      if (isset ($row[$populating_key])) {
        $populating_id = $row[$populating_key];
        if ($remove_populating_id) unset($row[$populating_key]);

        if ($remove_null) foreach ($row as $key => $val) if (is_null($val)) unset($row[$key]);
        
        if ($row) {
          if ($is_list) {
            if (!isset($populating_list[$populating_id][$element])) {
              $populating_list[$populating_id][$element] = array();
            }  
            array_push($populating_list[$populating_id][$element], $row);
          } else {
            $populating_list[$populating_id][$element][$key] = $val;
          }
        }
      } else {
        info(__FILE__, __FUNCTION__, __LINE__, "missing populating key in data row", $element, $populating_key, $row);
      }
    }
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, "populating non-array is not implemented", $element, $populating_key, $dataset);
  }
}
?>