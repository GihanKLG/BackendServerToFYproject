<?php
require_once 'db.php';
require_once 'config.php';
require_once 'session.php';
require_once 'util.php';
require_once 'db_user.php';

function db_institute_read($args)
{
  $institute_id_list = get_acl_institute_list($_SESSION['user_id'], $total, $args);
  $institute_info_list =  populate_institute_info($institute_id_list, $args);

  if ($institute_info_list) {
    populate_institute_activity_count($institute_info_list, $institute_id_list, $args);
    populate_institute_activity_history($institute_info_list, $institute_id_list, $args);
    succ_return(array_values($institute_info_list), true, true, count($institute_info_list));
  } 
}

function get_acl_institute_list($user_id, &$total = 0, $args)
{
  $start = 0;
  $size = DEFAULT_PAGE_SIZE;
  $no_limit = false;
  $limit_clause = "";

  if (isset($args['__no_limit__'])) $no_limit = $args['__no_limit__'];

  if (!$no_limit) {
    if (isset($args['__limit_start__'])) $start = $args['__limit_start__'];
    if (isset($args['__limit_size__'])) $size = $args['__limit_size__'];
    $limit_clause = "LIMIT $start, $size";
  }

  $query = "SELECT DISTINCT q.institute_id
    FROM (SELECT institute AS institute_id 
      FROM tbl_user_role tur
      WHERE user = ($user_id) 
      UNION 
      SELECT item AS institute_id 
      FROM acl_user_institute 
      WHERE accessee = ($user_id))q       
    LEFT JOIN tbl_institute ti ON ti.id = q.institute_id
    WHERE (ti.parent !=  'NULL')    
    $limit_clause";

  $db_resp = db_execute($query, $total);
  $institute_id_list = array_column($db_resp, 'institute_id');
  return $institute_id_list;
}

function populate_institute_info(&$institute_info_list, $args)
{
  $institute_id_csv_str = implode(", ", array_values($institute_info_list));
  $url_base = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];

  $query = "SELECT q.*
    FROM (SELECT ti.id AS institute_id, ti.name, ti.address, 
      CONCAT('$url_base', '/', tm.path, '/', tm.file) AS media, 
      tc1.contact AS mobile_number, tc2.contact 
      AS Landline, tu.name AS manager
      FROM cfg_institute_type cit, tbl_institute ti
      LEFT JOIN tbl_institute_media tim ON tim.institute = ti.id AND (
      tim.usage = (SELECT id FROM cfg_media_usage WHERE name = 'Profile') 
      AND tim.active = 1 ANd tim.institute IN ($institute_id_csv_str)
      ) LEFT JOIN tbl_media tm ON tm.id = tim.media
      LEFT JOIN tbl_institute_contact a ON a.institute = ti.id AND (
      a.type = (SELECT id FROM cfg_contact_type WHERE name = 'Mobile') 
      AND a.institute IN ($institute_id_csv_str)
      )LEFT JOIN tbl_contact tc1 ON tc1.id = a.contact
      LEFT JOIN tbl_institute_contact b ON b.institute = ti.id AND (
      b.type = (SELECT id FROM cfg_contact_type WHERE name = 'Landline') 
      AND b.institute IN ($institute_id_csv_str)
      )LEFT JOIN tbl_contact tc2 ON tc2.id = b.contact
      LEFT JOIN tbl_user_role tur ON tur.institute = ti.id AND (
      tur.role = (SELECT id FROM tbl_role WHERE name = 'Manager')
      AND tur.institute IN ($institute_id_csv_str)
      )LEFT JOIN tbl_user tu ON tu.id = tur.user
    WHERE ti.id IN ($institute_id_csv_str)
    Group BY ti.id ORDER BY ti.id) q";
          
  $institute_list = array();
  foreach(db_execute($query) as $row){
    $institute_id = $row['institute_id'];
    foreach($row as $key => $val) if (!is_null($val)) $institute_list[$institute_id][$key] = $val;
  };

  return $institute_list;
}

function populate_institute_activity_count(&$institute_info_list, $institute_id_list, $args)
{

  $group_by_clause = "GROUP BY q.institute, q.type";

  $date=date("Y-m-d");
  if(isset($args['date'])) {
    $date = $args['date'];
  }

  $next_date = date('Y-m-d', strtotime($date .' +1 day'));
  $date_for_delay_check = (date("Y-m-d") == $date) ? "CURRENT_TIME" : "'" . $next_date . "'";

  $user_id = $_SESSION['user_id'];
  $user_id_list = populate_item_list($user_id);
  $user_id_csv = implode(", ", $user_id_list);
  
  $role_list = get_acl_user_role_list($user_id);
  $institute_list = get_acl_user_institute_list($user_id);
  $institute_id_csv_str = implode(", ", $institute_list);
  
  $closed_clause = "AND ((ISNULL(q2.end) AND $date <= q.updated_ts AND q.updated_ts < $next_date) OR (q2.start IS NOT NULL AND DATE(q2.start)='$date') 
       OR (q2.start IS NOT NULL AND DATE(q2.start)<'$date' AND q2.status!='3') OR (DATE(q.updated_ts) = '$date' AND q2.status ='3'))";

  $union_list['user_user'] = "SELECT tur.institute, ta.type, ta.id, ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2 , tbl_user tu, tbl_user tu2
    LEFT OUTER JOIN tbl_user_role tur ON tur.user = tu2.id
  WHERE (((ta.assignee) IN ($user_id_csv)) OR ((ta.about) IN ($user_id_csv))) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'user' 
    AND ta.assignee = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user' 
    AND ta.about = tu2.id
  ";

  $union_list['user_role'] ="SELECT tur.institute, ta.type, ta.id, ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_role tr, tbl_user_role tur, tbl_user tu
  WHERE (ta.assignee) IN ($user_id_csv) AND ta.assignee_type = cal.id 
    AND LOWER(cal.name) = 'user' AND ta.assignee = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'role' 
    AND ta.about = tr.id AND tur.user = tu.id AND tur.institute IN ($institute_id_csv_str)";

  $union_list['user_institute'] = "SELECT ti.id as institute, ta.type, ta.id , ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_user tu, tbl_institute ti
  WHERE ta.assignee IN ($user_id_csv)  AND ta.assignee_type = cal.id 
    AND LOWER(cal.name) = 'user' AND ta.assignee = tu.id 
    AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute' AND ta.about = ti.id And ti.id in ($institute_id_csv_str)";

  $union_list['institute_user'] = "SELECT ti.id as institute, ta.type, ta.id, ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, cfg_assignment_level cal2, tbl_user tu
    LEFT OUTER JOIN tbl_user_role tur ON tur.user = tu.id
  WHERE ta.assignee IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user'";

  $union_list['institute_role'] = "SELECT ti.id as institute, ta.type, ta.id , ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, tbl_role tr, cfg_assignment_level cal2
  WHERE ta.assignee IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = tr.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'role'";

  $union_list['institute_institute'] = "SELECT ti2.id as institute, ta.type , ta.id , ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, cfg_assignment_level cal2, tbl_institute ti2
  WHERE ta.about IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = ti2.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute'";
    
  $union_list['role_institute'] = "SELECT ti.id as institute, ta.type, ta.id, ta.updated_ts, ta.updated_by
    FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_role tr, tbl_institute ti      
    WHERE ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' AND ta.assignee = tr.id
      AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute' AND ta.about = ti.id  ANd ta.about IN ($institute_id_csv_str)
      AND ta.assignee IN (
        SELECT tur.role FROM tbl_user_role tur WHERE tur.user IN ($user_id_csv)
      )";

  $union_list['role_user'] = " SELECT tur2.institute as institute, ta.type, ta.id, ta.updated_ts, ta.updated_by
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, 
    tbl_user_role tur2, tbl_role tr, tbl_user tu     
  WHERE tur2.user = ta.about AND  tur2.institute IN ($institute_id_csv_str) AND ta.assignee IN (
      SELECT tur.role FROM tbl_user_role tur WHERE tur.user IN ($user_id_csv)
    ) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' AND ta.assignee = tr.id 
    AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user' AND ta.about = tu.id AND ta.assignee = tr.id";

  $union_clause = implode(" UNION ", $union_list);

  $query = "SELECT q.institute as institute_id, cat.name AS type, COUNT(DISTINCT q.id) AS total, 
  SUM(IF (q2.end <  $date_for_delay_check AND NOT cas.closed, 1, 0)) AS delay 
  FROM cfg_activity_type cat, tbl_user tu, cfg_activity_status cas,($union_clause) q
  LEFT OUTER JOIN tbl_schedule_activity tsa on tsa.activity = q.id 
  LEFT OUTER JOIN tbl_schedule ts on ts.id = tsa.schedule
  LEFT OUTER JOIN (
        SELECT tpa.activity, tpa.status, tpa.start, tpa.end FROM tbl_prescription_activity tpa 
        UNION 
        SELECT tsa.activity, tsa.status, tsa.start, tsa.end FROM tbl_schedule_activity tsa
      ) q2 ON q.id = q2.activity  AND q2.end < '$next_date'
  WHERE q.type = cat.id AND q.updated_by = tu.id AND q2.status = cas.id AND q.institute IN ($institute_id_csv_str)
  $closed_clause $group_by_clause ";

  debug(__FILE__,__FUNCTION__,__LINE__, $query);

  populate_institute_dataset($institute_info_list, 'institute_activity_count', db_execute($query));
}


function populate_institute_activity_history(&$institute_info_list, $institute_id_list, $args) {

  $date=date("Y-m-d");
  if(isset($args['date'])) $date = $args['date'];  
  $next_date = date('Y-m-d', strtotime($date .' +1 day'));
  $user_id = $_SESSION['user_id'];
  $user_id_list = populate_item_list($user_id);
  $user_id_csv = implode(", ", $user_id_list);
  $role_list = get_acl_user_role_list($user_id);
  $institute_id_csv_str = implode(", ", array_values( $institute_id_list));

  $closed_clause = "AND ((ISNULL(q2.end) AND $date <= q.updated_ts AND q.updated_ts < $next_date) OR (q2.start IS NOT NULL AND DATE(q2.start)='$date') 
                OR (q2.start IS NOT NULL AND DATE(q2.start)<'$date' AND q2.status!='3') OR (DATE(q.updated_ts) = '$date' AND q2.status ='3'))";

  $union_list['user_user'] = "SELECT tur.institute, ta.type, ta.id, ta.updated_ts, ta.updated_by, 
    tu.name as assignee_name, tu2.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2 , tbl_user tu, tbl_user tu2
    LEFT OUTER JOIN tbl_user_role tur ON tur.user = tu2.id
  WHERE (((ta.assignee) IN ($user_id_csv)) OR ((ta.about) IN ($user_id_csv))) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'user' 
    AND ta.assignee = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user' 
    AND ta.about = tu2.id
  ";

  $union_list['user_role'] ="SELECT tur.institute, ta.type, ta.id, ta.updated_ts, ta.updated_by,
    tu.name as assignee_name, tr.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_role tr, tbl_user_role tur, tbl_user tu
  WHERE (ta.assignee) IN ($user_id_csv) AND ta.assignee_type = cal.id 
    AND LOWER(cal.name) = 'user' AND ta.assignee = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'role' 
    AND ta.about = tr.id AND tur.user = tu.id AND tur.institute IN ($institute_id_csv_str)";

  $union_list['user_institute'] = "SELECT ti.id as institute, ta.type, ta.id , ta.updated_ts, ta.updated_by,
    tu.name as assignee_name, ti.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_user tu, tbl_institute ti
  WHERE ta.assignee IN ($user_id_csv)  AND ta.assignee_type = cal.id 
    AND LOWER(cal.name) = 'user' AND ta.assignee = tu.id 
    AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute' AND ta.about = ti.id And ti.id in ($institute_id_csv_str)";

  $union_list['institute_user'] = "SELECT ti.id as institute, ta.type, ta.id, ta.updated_ts, ta.updated_by, 
    ti.name as assignee_name, tu.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, cfg_assignment_level cal2, tbl_user tu
    LEFT OUTER JOIN tbl_user_role tur ON tur.user = tu.id
  WHERE ta.assignee IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = tu.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user'";

  $union_list['institute_role'] = "SELECT ti.id as institute, ta.type, ta.id , ta.updated_ts, ta.updated_by, 
    ti.name as assignee_name, tr.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, tbl_role tr, cfg_assignment_level cal2
  WHERE ta.assignee IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = tr.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'role'";

  $union_list['institute_institute'] = "SELECT ti2.id as institute, ta.type , ta.id , ta.updated_ts, ta.updated_by,
    ti.name as assignee_name, ti2.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, tbl_institute ti, cfg_assignment_level cal2, tbl_institute ti2
  WHERE ta.about IN ($institute_id_csv_str) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' AND ta.assignee = ti.id
    AND ta.about = ti2.id AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute'";
    
  $union_list['role_institute'] = "SELECT ti.id as institute, ta.type, ta.id, ta.updated_ts, ta.updated_by,
    tr.name as assignee_name, ti.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, tbl_role tr, tbl_institute ti      
  WHERE ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' AND ta.assignee = tr.id
    AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'institute' AND ta.about = ti.id  ANd ta.about IN ($institute_id_csv_str)
    AND ta.assignee IN (
        SELECT tur.role FROM tbl_user_role tur WHERE tur.user IN ($user_id_csv)
      )";

  $union_list['role_user'] = " SELECT tur2.institute as ins, ta.type, ta.id, ta.updated_ts, ta.updated_by, 
    tr.name as assignee_name, tu.name as about_name
  FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, 
    tbl_user_role tur2, tbl_role tr, tbl_user tu     
  WHERE tur2.user = ta.about AND  tur2.institute IN ($institute_id_csv_str) AND ta.assignee IN (
      SELECT tur.role FROM tbl_user_role tur WHERE tur.user IN ($user_id_csv)
    ) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' AND ta.assignee = tr.id 
    AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user' AND ta.about = tu.id AND ta.assignee = tr.id";

  $union_clause = implode(" UNION ", $union_list);

  $query = "SELECT q.institute as institute_id, cat.name AS type, q.id, q.assignee_name, q.about_name, q.updated_ts 
  FROM cfg_activity_type cat, tbl_user tu, cfg_activity_status cas,($union_clause) q
  LEFT OUTER JOIN tbl_schedule_activity tsa on tsa.activity = q.id 
  LEFT OUTER JOIN tbl_schedule ts on ts.id = tsa.schedule
  LEFT OUTER JOIN (
        SELECT tpa.activity, tpa.status, tpa.end FROM tbl_prescription_activity tpa 
        UNION 
        SELECT tsa.activity, tsa.status, tsa.end FROM tbl_schedule_activity tsa
      ) q2 ON q.id = q2.activity  AND q2.end < '$next_date'
  WHERE q.type = cat.id AND q.updated_by = tu.id AND q2.status = cas.id $closed_clause 
  ORDER BY  q.institute, cat.id, q.id
  ";

  debug(__FILE__,__FUNCTION__,__LINE__, $query); 
  populate_institute_dataset($institute_info_list, 'institute_activity_history', db_execute($query));

}


function populate_institute_dataset(&$institute_info_list,  $element, $dataset, $is_list = true, $remove_populating_id = true)
{
  populate_dataset($institute_info_list, 'institute_id', $element, $dataset, $is_list, $remove_populating_id);
}

?>