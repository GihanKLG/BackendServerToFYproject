<?php
require_once 'db.php';
require_once 'db_user.php';

function populate_id_lists_and_get_activity_list($notification_list, &$activity_id_list, &$user_id_list) {
  foreach ($notification_list as $key => $val) {
    if (!in_array($val['activity'], $activity_id_list)) array_push($activity_id_list, $val['activity']);
    if (!in_array($val['updated_by'], $user_id_list)) array_push($user_id_list, $val['updated_by']);
  }
  $query = "select distinct a.id, a.type, a.assignee, 
    al1.name as assignee_type, a.about, al2.name as about_type, a.status
    from tbl_activity a, tbl_assignment_level al1, tbl_assignment_level al2 
    where al1.id = a.assignee_type and al2.id = a.about_type and a.id in (" . implode(", ", $activity_id_list) . ")";
  return db_query($query);
}

function get_activity_reference_map($notification_activity_list, $ref_type_map) {
  foreach ($notification_activity_list as $key => $val) {
    $assignee = $val['assignee'];
    $assignee_type = strtolower($val['assignee_type']);
    $about = $val['about'];
    $about_type = strtolower($val['about_type']);
    
    if (isset($ref_type_map[$assignee_type])) {
      array_push($ref_type_map[$assignee_type], $assignee);
    } else {
      $ref_type_map[$assignee_type] = array($assignee);
    }

    if (isset($ref_type_map[$about_type])) {
      array_push($ref_type_map[$about_type], $about);
    } else {
      $ref_type_map[$about_type] = array($about);
    }
  }
  return $ref_type_map;
}

function populate_reference_list($ref_type_map) {
  $ref_list = array();
  foreach ($ref_type_map as $key => $val) {
    $ref_list[$key] = array();
    $tbl_name = 'tbl_' . $key;    
    $query = "select distinct id, name from $tbl_name where id in (" . implode(", ", $val) . ")";
    $ref_list[$key]['list'] = db_query($query);
    $tbl_media = $tbl_name . "_media";
    $query = "select distinct nm.$key, m.id, m.path, m.file, mt.name as type, nm.active, mu.name as `usage` 
      from $tbl_media nm, tbl_media m, tbl_media_type mt, tbl_media_usage mu
      where m.type = mu.id and mt.id = m.type and m.id = nm.media and nm.$key in (" . implode(", ", $val) . ")";
    $ref_list[$key]['media'] = db_query($query);
    if ($key == 'user') {
      $tbl_role = $tbl_name . "_role";
      $query = "select distinct ur.$key, r.id, r.name from $tbl_role ur, tbl_role r where r.id = ur.role and ur.$key in (" . implode(", ", $val) . ")";
      $ref_list[$key]['role'] = db_query($query);
    }
  }
  return $ref_list;
}

function db_read_notification($args) {
  $user_id = $_SESSION[FIELD_USER_ID];
  $args['user'] = $user_id;
  $field_list = get_field_list(__FILE__);
  $total = 0;
  debug(__FILE__,__FUNCTION__,__LINE__, $field_list);

  $notification_list = db_search('tbl_notification', $field_list, $args, false, false, $total);
  $user_id_list = get_acl_user_list($user_id);
  $user_id_list = array_diff($user_id_list, array($user_id));
  $user_id_csv = implode(", ", $user_id_list);
  debug(__FILE__,__FUNCTION__,__LINE__, $user_id_csv);
  $date=date("Y-m-d");
  if(isset($args['date'])) {
    $date = $args['date'];
  }
  
  $query = "SELECT tn.* FROM tbl_notification tn, cfg_notification_status cns 
    WHERE tn.user = $user_id AND cns.id = tn.status AND LOWER(cns.name) != 'done' AND DATE(tn.updated_ts) <= '$date'";
  $other_notification_list = db_execute($query);

  $activity_list = array_column($notification_list, 'activity');
  $activity_list_csv = implode(", ", $activity_list);
  debug(__FILE__,__FUNCTION__,__LINE__, $activity_list_csv);
  $activity_info = db_get_activity_info($activity_list_csv);
  $final_list = modifyArray($notification_list, $activity_info);
  debug(__FILE__,__FUNCTION__,__LINE__, $activity_info);

  $other_final_list = array();
  if($other_notification_list) {

    $other_activity_list = array_column($notification_list, 'activity');
    $other_activity_list_csv = implode(", ", $activity_list);
    
    $other_activity_info = db_get_activity_info($other_activity_list_csv);
    $other_final_list = modifyArray($other_notification_list, $other_activity_info);
  }

  if(!$other_notification_list) $other_final_list = null; 

  $notification_status_list = db_search('tbl_notification_status', array('id', 'name'), array(), false, false);
  
  $activity_id_list = $user_id_list = array();
  $notification_activity_list = populate_id_lists_and_get_activity_list($notification_list, $activity_id_list, $user_id_list);
  
  $activity_status_list = db_search('tbl_activity_status', array('id', 'name'), array(), false, false);

  $ref_type_map = array('user' => $user_id_list);
  $ref_type_map = get_activity_reference_map($notification_activity_list, $ref_type_map);
  
  $ref_list = populate_reference_list($ref_type_map);

  $activity_list = array('list' => $notification_activity_list, 'status' => $activity_status_list);

  succ_return(array(
    'list' => $final_list, 
    'status' => $notification_status_list, 
    'activity' => $activity_list,
    'ref' => $ref_list
  ), true, true, $total);
}

function db_read_other_notification($args) {
  $user_id = $_SESSION[FIELD_USER_ID];
  $args['user'] = $user_id;
  $field_list = get_field_list(__FILE__);
  $total = 0;
  debug(__FILE__,__FUNCTION__,__LINE__, $field_list);

  $user_id_list = populate_item_list($user_id);
  $user_id_list = array_diff($user_id_list, array($user_id));
  $user_id_csv = implode(", ", $user_id_list);
  debug(__FILE__,__FUNCTION__,__LINE__, $user_id_csv);
  $date=date("Y-m-d");
  if(isset($args['date'])) {
    $date = $args['date'];
  }
  
  $query = "SELECT tn.* FROM tbl_notification tn, cfg_notification_status cns 
    WHERE user IN ($user_id_csv) AND tn.status = cns.id AND LOWER(cns.name) != 'done' AND DATE(tn.updated_ts) <= '$date'";
  $other_notification_list = db_execute($query);
  
  $other_final_list = array();
  if($other_notification_list) {

    $other_activity_list = array_column($other_notification_list, 'activity');
    $other_activity_list_csv = implode(", ", $other_activity_list);
    
    $other_activity_info = db_get_activity_info($other_activity_list_csv);
    $other_final_list = modifyArray($other_notification_list, $other_activity_info);
  }

  if(!$other_notification_list) $other_final_list = null;
  
  $notification_status_list = db_search('tbl_notification_status', array('id', 'name'), array(), false, false);
  
  $activity_id_list = $user_id_list = array();
  $notification_activity_list = populate_id_lists_and_get_activity_list($other_final_list, $activity_id_list, $user_id_list);
  
  $activity_status_list = db_search('tbl_activity_status', array('id', 'name'), array(), false, false);

  $ref_type_map = array('user' => $user_id_list);
  $ref_type_map = get_activity_reference_map($notification_activity_list, $ref_type_map);
  
  $ref_list = populate_reference_list($ref_type_map);

  $activity_list = array('list' => $notification_activity_list, 'status' => $activity_status_list);

  succ_return(array(
    'list' => $other_final_list, 
    'status' => $notification_status_list, 
    'activity' => $activity_list,
    'ref' => $ref_list
  ), true, true, $total);
}

// function db_update_notification($args) {
//   $field_list = get_field_list(__FILE__);
//   $user_id = $_SESSION[FIELD_USER_ID];
//   $control = array('user' => $user_id, 'id' => $args['id']);
//   return db_update('tbl_notification', $field_list, $args, true, true, $control);
// }

function db_delete_notification($args) {
  $field_list = get_field_list(__FILE__);
  $user_id = $_SESSION[FIELD_USER_ID];
  $control = array('user' => $user_id, 'id' => $args['id']);
  return db_delete('tbl_notification', $field_list, $control);
}

function db_get_activity_info($activity_list_csv) {

  $url_base = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];

  $query = "SELECT ta.id as activity, cat.name AS type_name, CASE WHEN LOWER(cal1.name) = 'user' THEN (SELECT name 
    FROM tbl_user WHERE id=ta.assignee
    ) WHEN LOWER(cal1.name) = 'institute' THEN (SELECT name FROM tbl_institute WHERE id=ta.assignee) 
    WHEN LOWER(cal1.name) = 'role' THEN (SELECT name FROM tbl_role WHERE id=ta.assignee) END AS assignee, cal1.name AS assignee_type, CASE WHEN LOWER(cal2.name) = 'user' THEN (SELECT name 
    FROM tbl_user WHERE id=ta.about
    ) WHEN LOWER(cal2.name) = 'institute' THEN (SELECT name FROM tbl_institute WHERE id=ta.about) 
    WHEN LOWER(cal2.name) = 'role' THEN (SELECT name FROM tbl_role WHERE id=ta.about) END AS about_name, cal2.name AS about_type,
    CASE WHEN LOWER(cal2.name) = 'user' THEN (SELECT CONCAT('$url_base', '/', tm.path, '/', tm.file) FROM tbl_user_media tum 
    LEFT OUTER JOIN tbl_media tm ON tm.id=tum.media
    LEFT OUTER JOIN cfg_media_usage cmu ON cmu.id=tum.usage
    WHERE tum.user=ta.about AND LOWER(cmu.name)='profile') WHEN LOWER(cal2.name) = 'institute' THEN (SELECT CONCAT('$url_base', '/', tm.path, '/', tm.file)
    FROM tbl_institute_media tim LEFT OUTER JOIN tbl_media tm ON tm.id=tim.media 
    LEFT OUTER JOIN cfg_media_usage cmu ON cmu.id=tim.usage
    WHERE tim.institute=ta.about AND LOWER(cmu.name)='profile') WHEN LOWER(cal2.name) = 'role'
    THEN null END AS url
    FROM tbl_activity ta 
      INNER JOIN cfg_assignment_level cal1 ON ta.assignee_type = cal1.id
      INNER JOIN cfg_assignment_level cal2 ON ta.about_type = cal2.id
      INNER JOIN cfg_activity_type cat ON ta.type = cat.id
    WHERE ta.id IN ($activity_list_csv)";
  $result = db_execute($query);
  debug(__FILE__,__FUNCTION__,__LINE__, $query);
  
  return $result;
}

function modifyArray($a, $b) {
  $entriesArray = array();
  $i=0;
  foreach ($a as $names) {
      foreach ($b as $authors) {
          if ($names['activity'] === $authors['activity']) {
               $result[$i] = array_merge($names,$authors);
               $i=$i+1;                 
          }
      }
  }
  return $result;
}

function db_update_notification_status($args) {
  if(!isset($args['status']) OR !isset($args['id'])) exit(fail_return("missing input feilds", false));
  $status = $args['status'];
  $notification_id = $args['id'];
  $query = "UPDATE tbl_notification 
    SET status = (SELECT id FROM cfg_notification_status 
    WHERE LOWER(name) = '$status') WHERE id = '$notification_id'";
  $result = db_execte($query);
  
  exit(succ_return("successfully updated", false));
}
?>