<?php
require_once 'db.php';
require_once 'db_institute.php';

function populate_user_profile(&$user_list, $date) {
  populate_user_info($user_list);
  populate_user_para($user_list);
  populate_user_role($user_list);
  populate_user_contact($user_list);
  populate_user_media($user_list);
  populate_acl_user_user_info($user_list);
  populate_user_activity_count($user_list, $date);
  populate_user_last_activity_detail($user_list, $date); 
  populate_user_activity_history($user_list, $date);
  populate_user_notification_count($user_list);
  populate_user_summary($user_list);
  populate_user_kpi($user_list, $date);
}     

function db_forgot_password($args) {

  $sub = "change password";
  //$email = "lakshitha.16@itfac.mrt.ac.lk";
  $email = $args['email'];
 
  $query = "SELECT id FROM tbl_contact WHERE contact = '$email'";
  $result = db_execute($query);
  
  if($result) {
    $date=date("Y-m-d");
    $hash = password_hash($date, PASSWORD_DEFAULT);
    $query = "UPDATE tbl_contact SET fogotton_password = '$hash' WHERE contact = '$email'";  
    $result = db_execute($query);
    $msg = "http://localhost:8100/forgot_password/'$hash'";
    $result = mail($email,$sub,$msg);
    debug(__FILE__,__FUNCTION__,__LINE__, $result);
    if(!$result) exit(fail_return("Email sending failed...", false)); 
    else exit(succ_return("Email sending succesfully...", false)); 
  }
  else exit(fail_return("invalid email address", false));
}

function db_update_password($args) {
  $key = $args['key'];
  $email = $args['email'];
  $user_name = $args['user_name'];
  $password = $args['password'];
  $password = password_hash($password, PASSWORD_DEFAULT);

  $query = "SELECT fogotton_password FROM tbl_contact WHERE contact = '$email'";
  $result = db_execute($query);
  $result = array_shift($result);
  $result = $result['key'];

  if($key == $result) {
    $query = "UPDATE tbl_login_para tlp
      INNER JOIN tbl_login tl ON tl.id = tlp.login
      SET tlp.val = '$user_name'
      WHERE tl.user = (SELECT tuc.user FROM cfg_contact_type cct, tbl_contact tc 
      INNER JOIN tbl_user_contact tuc ON tuc.contact = tc.id 
      WHERE tc.contact = '$email' AND tuc.type = cct.id AND LOWER(cct.name) = 'email') AND tlp.para=(SELECT id FROM 
       cfg_login_para WHERE LOWER(name) = 'name')";
    $result = db_execute($query);   
    $query = "UPDATE tbl_login_para tlp
    INNER JOIN tbl_login tl ON tl.id = tlp.login
    SET tlp.val = '$password'
    WHERE tl.user = (SELECT tuc.user FROM cfg_contact_type cct, tbl_contact tc 
    INNER JOIN tbl_user_contact tuc ON tuc.contact = tc.id 
    WHERE tc.contact = '$email' AND tuc.type = cct.id AND LOWER(cct.name) = 'email') AND tlp.para=(SELECT id FROM 
     cfg_login_para WHERE LOWER(name) = 'pass')";
    $result = db_execute($query); 
  } else exit(fail_return(ERR_AUTHENTICATION, false));
}

function db_user_read($args) {
  $total = 0;
  $user_id_list = get_acl_user_list($_SESSION['user_id'], $total, $args);
  $user_list = get_user_by_id($user_id_list);
  $date = isset($args['date']) ? $args['date'] : date("Y-m-d");

  if ($user_list) {
    populate_user_profile($user_list, $date);
    populate_user_session_list($user_list);
  }
  
  succ_return(array_values($user_list), true, true, $total);
}

function db_login($args) {
  $date = isset($args['date']) ? $args['date'] : date("Y-m-d");
  $args['date'] = $date;
  
  populate_user_id($user_list, 'user-pass', $args);
  populate_user_id($user_list, 'device', $args);

  if ($user_list) {
    populate_user_profile($user_list, $date);
    
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

function db_user_add($args) {

  $user_id = $_SESSION['user_id'];
  $add_role_id = get_input_role_id($args);
  if (check_user_add_permission($user_id, $add_role_id)) {
    if (add_tbl_user($args)) {
      add_tbl_user_role($args);
      add_tbl_user_contact($args);
      add_tbl_user_media($args);
      add_tbl_user_careplan($args);
      add_tbl_user_info($args);
      add_tbl_user_inventory_tx($args);
      add_tbl_user_para($args);
      add_tbl_user_para_item($args);
      add_device_id($args);
      succ_return($args);
    } else {
      fail_return($args['response']);
    }
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, $user_id, $add_role_id, $args);
    fail_return(ERR_PERMISSION_DENIED);
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

function add_tbl_user_para_item($args){

  if(isset($args['para_item_id'])){
    $add_user_id = $args['add_user_id'];
    $add_para_item_id = $args['para_item_id'];
    $add_para_value = $args['para_item_val'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ?
    "Test Data - $my_user_id/$add_user_id" : "";

    $query = " INSERT INTO `tbl_user_para_item` (
      `id`, `user`, `para_item`, `value`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_user_id, $add_para_item_id, $add_para_value, NULL, NULL, $my_user_id, '$add_comment'
    )";

    db_execute($query);
  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'tbl_user_para_item');
  }
}

function add_tbl_user_para($args){
 
  if(isset($args['para_id'])){
    $add_user_id = $args['add_user_id'];
    $add_para_id = $args['para_id'];
    $add_para_val = $args['para_val'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? 
    "Test Data - $my_user_id/$add_user_id" : "";

    $query = " INSERT INTO `tbl_user_para` (
      `id`, `user`, `para`, `val`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_user_id, $add_para_id, $add_para_val, NULL, NULL, $my_user_id, '$add_comment'
    )";

    db_execute($query);
  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED,'tbl_user_para');
  }
}

function add_tbl_user_inventory_tx($args){

  if(isset($args['item'])){
    $add_user_id = $args['add_user_id'];
    $add_item_id = $args['item'];
    $add_quantity = $args['quantity'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['__test__'])) ? $args['__test__'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? 
    "Test Data - $my_user_id/$add_user_id" : "";

    $query = " INSERT INTO `tbl_user_inventory_tx` (
      `id`, `user`, `item`, `quantity`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_user_id, $add_item_id, $add_quantity, NULL, NULL, $my_user_id, '$add_comment'
    )";
    db_execute($query);
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_PARA_NOT_DEFINED, 'user_inventory_tx');
  }
}

function add_tbl_user_info($args){

  if(isset($args['info_id'])){
    $add_user_id = $args['add_user_id'];
    $add_info_id = $args['info_id'];
    $add_info_val = $args['info_val'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['__test__'])) ? $args['__test__'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? 
      "Test Data - $my_user_id/$add_user_id" : "";
    $query = " INSERT INTO `tbl_user_info` (
      `id`, `user`, `info`, `val`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUE (
      NULL, $add_user_id, $add_info_id, '$add_info_val', NULL, NULL, $my_user_id, '$add_comment'
    )";
    db_execute($query);
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_PARA_NOT_DEFINED, 'careplan');
  }
}

function add_tbl_user_careplan($args){
  if(isset($args['careplan'])){
    $add_user_id = $args['add_user_id'];
    $add_careplan_id = $args['careplan'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['__test__'])) ? $args['__test__'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? "Test Data" : "";
    $query = " INSERT INTO `tbl_user_careplan` (
        `id`, `user`, `careplan`, `created_ts`, `updated_ts`, `updated_by`, `comment`  
      ) VALUE (
        NULL, $add_user_id, $add_careplan_id, NULL, NULL, $my_user_id, '$add_comment'
      )";
    db_execute($query);
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_PARA_NOT_DEFINED, 'careplan');
  }
}

function add_tbl_user_media($args){

  if (isset($args['media'])) {
    $add_user_id = $args['add_user_id'];
    $add_media_id = add_tbl_media($args);
    $add_media_usage = $args['media_usage'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ?
      "Test Data" : "";
    $query = " INSERT INTO `tbl_user_media` (
        `id`, `user`, `media`, `usage`, `active`, `created_ts`, `updated_ts`, `updated_by`, `comment`
      ) VALUE (
        NULL, $add_user_id, $add_media_id, $add_media_usage, 1, NULL, NULL, $my_user_id, '$add_comment'
      )
      ";
    db_execute($query);
  } else {
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'contact');
  }  
}

function add_tbl_media($args) {
  $media = $args['media'];
  $media_details = array();
  $media_details = find_media_details($args['media']);
  $type = $media_details['0'];
  $path = $media_details['1'];
  $my_user_id = $_SESSION['user_id'];
  $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
  $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? "Test Data" : "";
  $query = "INSERT INTO `tbl_media` (
    `id`, `path`, `file`, `type`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    ) VALUES ( 
      NULL, '$path', '$media', '1', NULL, NULL, '$my_user_id', '$add_comment'
    )";

  db_execute($query);
  $last_id = db_execute($query= "select max(id) as id from tbl_media");
  return $last_id['0']['id'];
}

function find_media_details ($media) {
  $type = 0;
  $path = '';
  $ext = strrchr($media, ".");
  
  switch($ext){
    case '.jpg': case '.jpeg': case '.png':
      $type = 1;
      $path = '60plus/svr/images';
      break;
    case '.mp4':
      $type = 2;
      $path = '60plus/svr/videos';
      break;
    case '.pdf':
      $type = 4;
      $path = '60plus/svr/pdf';
      break;
    case '.ppt':
      $type = 5;
      $path = '60plus/svr/PPT';
      break;
    default:
    exit(fail_return(ERR_UNKNOWN_ACTION, false));
  }

  if (isset($type) && isset($path)) {
    return array($type, $path);
  }else{
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'find_media_details');
  }
}

function add_tbl_user_contact($args){
  if (isset($args['contact'])){ 
    $add_user_id = $args['add_user_id'];
    $add_contact_id = add_tbl_contact($args);
    $add_type = $args['contact_type'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ?
      "Test Data" : "";
    $query = " INSERT INTO  `tbl_user_contact` (
        `id`, `user`, `contact`, `type`, `created_ts`, `updated_ts`, `updated_by`, `comment`
      ) VALUES (
        NULL, $add_user_id, $add_contact_id,  $add_type, NULL, NULL, $my_user_id, '$add_comment'
      )";
    db_execute($query);
  } else {
    warn(__FILE__,__FUNCTION__,__LINE__, ERR_PARA_NOT_DEFINED, 'contact');
  }
}

function add_tbl_contact($args) {
  $add_contact = $args['contact'];
  $my_user_id = $_SESSION['user_id'];
  $add_test = (isset($args['_test_'])) ? $args['_test_'] : false;
  $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? "Test Data - $my_user_id" : "";

  $query = " INSERT INTO `tbl_contact` (
    `id`, `contact`, `created_ts`, `updated_ts`, `updated_by`, `comment`
    )
    VALUES (
      NULL, $add_contact, NULL, NULL, $my_user_id, '$add_comment'
    )";
  db_execute($query);

  $last_id = db_execute($query= "select max(id) as id from tbl_contact");
  return $last_id['0']['id'];
}
function add_tbl_user_role($args) {
  if (isset($args['institute'])) {
    $add_institute_id = $args['institute'];
    $add_user_id = $args['add_user_id'];
    $add_role_id = $args['add_role_id'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['__test__'])) ? $args['__test__'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? 
      "Test Data - $my_user_id/$add_user_id/$add_role_id/$add_institute_id" : "";
    $query = "INSERT INTO `tbl_user_role` (
      `id`, `user`, `role`, `institute`, `created_ts`, `updated_ts`, `updated_by`, `comment`
      ) VALUES (NULL, $add_user_id, $add_role_id, $add_institute_id, NULL, NULL, $my_user_id, '$add_comment')";
    db_execute($query);
  } else {
    warn(__FILE__, __FUNCTION__, __LINE__, ERR_PARA_NOT_DEFINED, 'institute');
  }
}

function add_tbl_user(&$args) {
  if (isset($args['user_name'])) {
    $add_user_name = $args['user_name'];
    $my_user_id = $_SESSION['user_id'];
    $add_test = (isset($args['__test__'])) ? $args['__test__'] : false;
    $add_comment = (isset($args['comment'])) ? $args['comment'] : ($add_test) ? "Test Data - $my_user_id/$add_user_name" : "";
    $query = "INSERT INTO `tbl_user` (`id`, `name`, `created_ts`, `updated_ts`, `updated_by`, `comment`) VALUES (
      NULL, '$add_user_name', NULL, NULL, $my_user_id, '$add_comment')";
    return $args['add_user_id'] = db_execute($query);
  } else {
    $args['response'] = array(ERR_PARA_NOT_DEFINED, 'user_name');
    return false;
    $array1[$i]['Done']=$arr1['Done'];
    $i++; 
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

function change_description($args) {
  $id=$args['gardiant_id'];
  $query1= "SELECT * from tbl_image WHERE gardiant_id = $id";
  $num_rows = count($query1);

  if ($num_rows > '0') {
    $query ="UPDATE tbl_image SET description = 'not_profile' WHERE gardiant_id = '$id'";
    $conn = get_conn();
    $result = mysqli_query($conn, $query);
  }
}

function db_image_insert($tbl, $field_list, $val_list) {
  $target = "images/";
  $conn = get_conn();
  
  if(isset($args['image'])) {
    $image = $_FILES['image']['name'];
    $gardiant_id =  $val_list['gardiant_id'];
    $description = $val_list['description'];
    $updated_by = $val_list['updated_by'];
    $path = pathinfo($image);
    $filename = $path['filename'];
    $ext = $path['extension'];
    $temp_name = $_FILES['image']['tmp_name'];
    $path_filename_ext = $target.$filename.".".$ext;
    
    $sql = "INSERT INTO tbl_image (gardiant_id, image, description, updated_by) VALUE ('$gardiant_id', '$image', '$description', '$updated_by')";
    $result=mysqli_query($conn, $sql);
    
    if (file_exists($path_filename_ext)) {
        $error = ERR_EXISTENCE;
        exit(fail_return($error, false));
    } else {
      $error = ERR_SUCCESS; 
      move_uploaded_file($temp_name,$path_filename_ext);
      exit(fail_return($error, true));
    }
  } else {
    $error = ERR_DB_INSERT;
    exit(fail_return($error, false));
  }
}

function update_user_profile($args, $echo = true, $json = true) {

  if(!isset($args['contact']) || !isset($args['email']) || !isset($args['name']) || !isset($args['image'])) {
    exit(fail_return("missing some input feilds", false));
  }
  $contact = $args['contact'];
  $email = $args['email'];
  $name = $args['name'];
  $id = $_SESSION['user_id'];
  $image = $args['image'];
  debug(__FILE__,__FUNCTION__,__LINE__, $args);

  $query1 = "UPDATE tbl_contact 
  SET contact = '$contact' 
  WHERE id IN (SELECT uc.contact 
      FROM tbl_user_contact uc,  cfg_contact_type cct 
      WHERE uc.user = $id and cct.id = uc.type AND LOWER(cct.name) = 'mobile')" ;
  debug(__FILE__,__FUNCTION__,__LINE__, $query1);

  $query2 = "UPDATE tbl_contact 
  SET contact = '$email' 
  WHERE id IN (SELECT uc.contact 
      FROM tbl_user_contact uc, cfg_contact_type cct  
      WHERE uc.user = $id and cct.id = uc.type AND LOWER(cct.name) = 'email')" ;
  debug(__FILE__,__FUNCTION__,__LINE__, $query2);

  $query3 = "UPDATE tbl_user
  SET name = '$name'
  WHERE id = $id";
  debug(__FILE__,__FUNCTION__,__LINE__, $query3);

  $file = upload_image($id, $image);
  debug(__FILE__,__FUNCTION__,__LINE__, $file);
  
  $query4 = "UPDATE tbl_media tm
  SET tm.file = '$file'
  WHERE id IN (SELECT tum.media 
      FROM tbl_user_media tum, cfg_media_usage cmu
      WHERE tum.user = $id and tum.usage = cmu.id and LOWER(cmu.name) = 'profile')" ;
  debug(__FILE__,__FUNCTION__,__LINE__, $query4);

  $total = '0';

  $result1 = db_execute($query1); 
  $result2 = db_execute($query2); 
  $result3 = db_execute($query3);
  $result4 = db_execute($query4);
  
  return succ_return(OK_DATA_UPDATE, $echo, $json, $total);

}

function upload_image($id,$image) {
  $target = "images/";
  $file = $_FILES['image']['name'];
  $path = pathinfo($file);
  $filename = $path['filename'];
  $ext = $path['extension'];
  $temp_name = $_FILES['image']['tmp_name'];
  $path_filename_ext = $target.$id.".".$filename.".".$ext;
  if (!file_exists($path_filename_ext)) {
  move_uploaded_file($temp_name,$path_filename_ext);
  } 
  return "$filename_$ext";
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

function populate_user_media(&$user_list) {
  if ($user_list) {
    $url_base = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
    $query = "SELECT um.user AS user_id,  mu.name as usage_name, m.id AS media_id, 
    CONCAT('$url_base', '/', m.path, '/', m.file) AS url,m.type AS media_type_id,
    m.path, m.file, mt.name AS media_type_name
    FROM tbl_media m, tbl_user_media um, cfg_media_type mt, cfg_media_usage mu
      WHERE um.media = m.id AND um.user IN (" . implode(", ", array_keys($user_list)) . ") 
      AND mt.id = m.type AND mu.id = um.usage And um.active = 1";
      debug(__FILE__,__FUNCTION__,__LINE__, $query);
    populate_user_dataset($user_list, 'media', db_execute($query));
  }
}

function populate_acl_user_user_info(&$user_list){
  if ($user_list) {
    $query = "SELECT auu.item as user_id, tu.name, tr.name as role, tui.val as Address,
     tc1.contact as Mobile, tc2.contact as Home, tc3.contact as Office, tc4.contact as Email
        FROM tbl_user tu
        LEFT JOIN acl_user_user auu on auu.accessee = tu.id
        LEFT join tbl_user_contact tuc1 ON tuc1.user = tu.id 
        AND tuc1.type = (SELECT id from cfg_contact_type WHERE name = 'Mobile')
        LEFT JOIN tbl_contact tc1 on tc1.id = tuc1.contact
        LEFT join tbl_user_contact tuc2 ON tuc2.user = tu.id 
        AND tuc1.type = (SELECT id from cfg_contact_type WHERE name = 'Home')
        LEFT JOIN tbl_contact tc2 on tc2.id = tuc2.contact
        LEFT join tbl_user_contact tuc3 ON tuc3.user = tu.id 
        AND tuc3.type = (SELECT id from cfg_contact_type WHERE name = 'Office')
        LEFT JOIN tbl_contact tc3 on tc3.id = tuc3.contact
        LEFT join tbl_user_contact tuc4 ON tuc4.user = tu.id 
        AND tuc4.type = (SELECT id from cfg_contact_type WHERE name = 'Email')
        LEFT JOIN tbl_contact tc4 on tc4.id = tuc4.contact
        LEFT join tbl_user_role tur on tur.user = auu.accessee 
        LEFT join tbl_role tr on tr.id = tur.role
        LEFT join tbl_user_info tui ON tui.user = tu.id 
        AND tui.info = (SELECT id from cfg_user_para WHERE name = 'Address')
        WHERE auu.item IN (" . implode(", ", array_keys($user_list)) . ")";

    populate_user_dataset($user_list, 'acl_user_info', db_execute($query));
  }
}

function populate_user_kpi(&$user_list, $date) {
  if ($user_list) {
    $query = "SELECT overall.user_id, today.count AS today_count, overall.count AS overall_count,
        overall.count/total.count AS overall_kpi 
      FROM (
        SELECT tk.user AS user_id, SUM(tk.activity_count) AS count FROM tbl_kpi tk 
        WHERE tk.user IN (" . implode(", ", array_keys($user_list)) . ") GROUP BY tk.user
      ) overall, (
        SELECT tk.user AS user_id, SUM(tk.activity_count) AS count FROM tbl_kpi tk 
        WHERE tk.user IN (" . implode(", ", array_keys($user_list)) . ") AND tk.date = '$date'
        GROUP BY tk.user
      ) today, (SELECT SUM(tk.activity_count) AS count FROM tbl_kpi tk) total 
      WHERE today.user_id = overall.user_id";
    populate_user_dataset($user_list, 'kpi', db_execute($query));
  }
} 

function populate_user_summary(&$user_list) {
  $user_summary_count = get_user_summary_count($user_list);
  populate_user_dataset($user_list, 'summary', $user_summary_count);
}

function get_user_summary_count($user_list) {
  $user_summary_count_list = array(); 
  $simple_institute_id_list = get_user_institute_id_list($user_list);
  
  foreach ($simple_institute_id_list as $key => $val) {
    array_push($user_summary_count_list, array('user_id' => $key, 'centers' => count(array_unique($val))));
  }
  
  $user_summary_count_list = array_merge(
    $user_summary_count_list, 
    get_user_role_count_list($user_list, $simple_institute_id_list)
  );
  return $user_summary_count_list;
}

function get_user_role_count_list($user_list, $simple_institute_id_list) {
  $ret = array();
  foreach ($simple_institute_id_list as $user_id => $institute_id_list) {
    $query = "SELECT user_id, q.role AS role_id, tr.name AS role_name, COUNT(q.user) AS count 
      FROM (
        SELECT $user_id AS user_id, auu.item AS user, tur.role AS role
        FROM acl_user_user auu, tbl_user_role tur WHERE tur.user = auu.item AND auu.accessee = $user_id
        UNION
        SELECT $user_id AS user_id, tur.user AS user, tur.role AS role
        FROM tbl_user_role tur WHERE tur.institute IN (" . implode(", ", $institute_id_list) . ")
      ) q, tbl_role tr WHERE tr.id = q.role GROUP BY user_id, role";
      
    $ret = array_merge($ret, db_execute($query));
  }
  return $ret;
}

function get_user_institute_id_list($user_list) {
  $simple_institute_id_list = array();   
  if ($user_list) {
    $user_institute_id_list = array();
    $query = "SELECT tur.user AS user_id, tur.institute AS institute_id 
      FROM tbl_user_role tur WHERE tur.user IN (" . implode(", ", array_keys($user_list)). ")";
    $user_institute_id_list = array_merge($user_institute_id_list, db_execute($query));

    $query = "SELECT aui.accessee AS user_id, aui.item AS institute_id 
      FROM acl_user_institute aui WHERE aui.accessee IN (" . implode(", ", array_keys($user_list)). ")";
    $user_institute_id_list = array_merge($user_institute_id_list, db_execute($query));
    
    $sub_institute_id_list = array();
    foreach ($user_institute_id_list as $user_institute) {
      if (isset($sub_institute_id_list[$user_institute['user_id']])) {
        array_push($sub_institute_id_list[$user_institute['user_id']], $user_institute['institute_id']);
      } else {
        $sub_institute_id_list[$user_institute['user_id']] = array($user_institute['institute_id']);
      }
    };

    foreach ($sub_institute_id_list as $user_id => $parent_id_list) {
      do {
        $query = "SELECT $user_id AS user_id, ti.id AS institute_id FROM tbl_institute ti 
          WHERE ti.parent IN (" . implode(", ", $parent_id_list) . ")";
        $db_resp = db_execute($query);
        $user_institute_id_list = array_merge($user_institute_id_list, $db_resp);
        $parent_id_list = array_column($db_resp, 'institute_id');
      } while ($parent_id_list);
    }

    foreach ($user_institute_id_list as $user_institute) {
      if (isset($simple_institute_id_list[$user_institute['user_id']])) {
        array_push($simple_institute_id_list[$user_institute['user_id']], $user_institute['institute_id']);
      } else {
        $simple_institute_id_list[$user_institute['user_id']] = array($user_institute['institute_id']);
      }
    };
  }

  return $simple_institute_id_list;
}

function populate_user_activity_count(&$user_list, $date) {
  if ($user_list) {
    $next_date = date('Y-m-d', strtotime($date .' +1 day'));
    $date_for_delay_check = (date("Y-m-d") == $date) ? "CURRENT_TIME" : "'" . $next_date . "'";
    $id = implode(", ", array_keys($user_list));
    $user_id_csv = populate_item_list($id);
    
    debug(__FILE__,__FUNCTION__,__LINE__, $user_id_csv);
    debug(__FILE__, __FUNCTION__, __LINE__, $next_date);
    debug(__FILE__, __FUNCTION__, __LINE__, $id);
    
    $institute_list = get_acl_user_institute_list($id);
    $institute_list_cvs = implode(", ", $institute_list);
    debug(__FILE__,__FUNCTION__,__LINE__, $institute_list_cvs);

   $role_list = get_acl_user_role_list($id);
  $role_list_cvs = implode(", ", $role_list);
  debug(__FILE__,__FUNCTION__,__LINE__, $role_list_cvs);

   $closed_clause = "AND ((q2.start IS NOT NULL AND DATE(q2.start)='$date') 
  		OR (q2.start IS NOT NULL AND DATE(q2.start)<'$date' AND q2.status!='3') OR (DATE(q2.updated_ts) = '$date' AND q2.status ='3'))";

    $query = "SELECT cat.name as type, COUNT(DISTINCT q2.id) AS total, 
        SUM(IF (q2.end < $date_for_delay_check AND NOT cas.closed, 1, 0)) AS delay 
      FROM (
        SELECT ta.assignee AS user_id, ta.type, ta.id
          FROM tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2
          WHERE ta.assignee_type = cal.id AND (ta.assignee in (" . implode(", ", $user_id_csv) . ") AND LOWER(cal.name) = 'user')
          UNION
          SELECT tur2.user AS user_id, ta.type, ta.id
          FROM 
            tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, 
            tbl_user_role tur, tbl_user_role tur2
          WHERE ta.assignee = tur2.role AND tur2.user IN (" . implode(", ", $user_id_csv) . ") AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' 
            AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'user' AND tur.user = ta.about 
            AND tur.institute IN (
              SELECT tur2.institute FROM tbl_user_role tur2 WHERE tur2.user IN (" . implode(", ", $user_id_csv) . ")
            )
          UNION
          SELECT tur2.user AS user_id, ta.type, ta.id
          FROM 
            tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2, 
            tbl_user_role tur, tbl_user_role tur2
          WHERE ta.assignee = tur2.role AND tur2.user IN (" . implode(", ", $user_id_csv) . ") AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'role' 
            AND ta.about_type = cal2.id AND LOWER(cal2.name) = 'role' AND tur.role = ta.about 
            AND tur.institute IN (
              SELECT tur2.institute FROM tbl_user_role tur2 WHERE tur2.user IN (" . implode(", ", $user_id_csv) . ")
            )
          UNION
          SELECT NULL AS user_id, ta.type, ta.id
          FROM
          tbl_activity ta, cfg_assignment_level cal, cfg_assignment_level cal2
          WHERE ta.assignee IN ($institute_list_cvs) AND ta.assignee_type = cal.id AND LOWER(cal.name) = 'institute' 

        ) q LEFT OUTER JOIN (
          SELECT tpa.id, tpa.activity, tpa.start, tpa.end, tpa.status, tpa.updated_ts FROM tbl_prescription_activity tpa 
          UNION 
          SELECT tsa.id, tsa.activity, tsa.start, tsa.end, tsa.status, tsa.updated_ts FROM tbl_schedule_activity tsa
        ) q2 ON q.id = q2.activity  AND q2.end < '$next_date', cfg_activity_type cat, cfg_activity_status cas 
      
      WHERE cas.id = q2.status AND cat.id = q.type  $closed_clause
      GROUP BY q.type";

     debug(__FILE__,__FUNCTION__,__LINE__, $query);
     $result=db_execute($query);
     $size = sizeof($result);
     for($i=0;$i<$size;$i++) {
      $result[$i]['user_id']=$id;
     }
    populate_user_dataset($user_list, 'activity', $result);
  }
}


function populate_user_notification_count(&$user_list) {
  if ($user_list) {
   foreach ($user_list as $user_id => $val) {

    $user_id_list = array_keys($user_list);
    $query = "SELECT n.user AS user_id, COUNT(n.id) AS own_count 
      FROM tbl_notification n, cfg_notification_status ns 
      WHERE ns.id = n.status AND n.user = $user_id AND LOWER(ns.name) != 'done'    
    GROUP BY n.user";
    debug(__FILE__,__FUNCTION__,__LINE__, $query);
    populate_user_dataset($user_list, 'notification', db_execute($query), false);
  }

    foreach ($user_list as $user_id => $val) {
      $acl_user_list = populate_item_list($user_id);
      $acl_user_list = array_diff($acl_user_list, array($user_id));
      if ($acl_user_list) {
        $query = "SELECT $user_id AS user_id, COUNT(n.id) AS other_count 
          FROM tbl_notification n, cfg_notification_status ns 
          WHERE ns.id = n.status AND n.user IN (" . implode(", ", $acl_user_list) . ") AND LOWER(ns.name) != 'done'";
    debug(__FILE__,__FUNCTION__,__LINE__, $query);
        populate_user_dataset($user_list, 'notification', db_execute($query), false);
      }
    }
  }
}

function get_acl_user_list($user_id, &$total = 0, $control = array()) {
  $start = 0;
  $size = DEFAULT_PAGE_SIZE;
  $no_limit = false;
  $limit_clause = "";
  $where_array = array();

  if (isset($control['__no_limit__'])) $no_limit = $control['__no_limit__'];
  
  if(!$no_limit) {
    if (isset($control['__limit_start__'])) $start = $control['__limit_start__'];
    if (isset($control['__limit_size__'])) $size = $control['__limit_size__'];
    $limit_clause = "LIMIT $start, $size";
  }
  
  if (isset($control['role'])) {
    $role = strtolower($control['role']);
    if (is_numeric($role)) {
      array_push($where_array, "tur.role = $role");
    } else if ($role) {
      array_push($where_array, "LOWER(tr.name) = '$role'");
    };
  }
  
  if (isset($control['user'])) {
    $user = strtolower($control['user']);
    array_push($where_array, "uid.user_id = $user");
  }

  $where_clause = "";
  if ($where_array) $where_clause = "WHERE " . implode(" AND ", $where_array);
  
  $query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT user_id 
    FROM (
      SELECT $user_id AS user_id
      UNION SELECT uu.item AS user_id FROM acl_user_user uu WHERE uu.accessee = $user_id 
      UNION SELECT ru.item AS user_id FROM acl_role_user ru, tbl_user_role ur2
        WHERE ru.item = ur2.user AND (ru.accessee, ur2.institute) IN (
          SELECT ur.role, ur.institute FROM tbl_user_role ur WHERE ur.user = $user_id
        ) 
      UNION SELECT ur.user AS user_id FROM tbl_user_role ur 
        WHERE ur.institute IN (SELECT ui.item FROM acl_user_institute ui WHERE ui.accessee = $user_id) 
      UNION SELECT tur2.user AS user_id FROM tbl_user_role tur2 WHERE (tur2.role, tur2.institute) IN (
        SELECT aur.item AS role, tur.institute FROM acl_user_role aur, tbl_user_role tur 
        WHERE aur.accessee = $user_id AND aur.accessee = tur.user
      )
      UNION SELECT ur2.user AS user_id FROM acl_role_institute ri, tbl_user_role ur2
        WHERE ri.item = ur2.institute AND (ri.accessee, ur2.institute) IN (
        SELECT ur.role, ur.institute FROM tbl_user_role ur WHERE ur.user = $user_id
      )
    ) uid LEFT OUTER JOIN tbl_user_role tur ON uid.user_id = tur.user
    LEFT OUTER JOIN tbl_role tr ON tur.role = tr.id $where_clause $limit_clause";

  $db_resp = db_execute($query, $total);
  $user_list = array_column($db_resp, 'user_id');

  return $user_list;
}

function populate_user_info(&$user_list) {
  if ($user_list) {
    $query = "SELECT u.id AS user_id, cup.id AS info_id, cup.name AS info_name, tui.val FROM tbl_user u 
      LEFT OUTER JOIN tbl_user_info tui ON tui.user = u.id 
      LEFT OUTER JOIN cfg_user_para cup ON tui.info = cup.id
    WHERE u.id IN (" . implode(", ", array_keys($user_list)) . ")";  

    populate_user_dataset($user_list, 'info', db_execute($query));
  }
}


function populate_user_para(&$user_list) {

  debug(__FILE__,__FUNCTION__,__LINE__, $user_list);
  if ($user_list) {
    $query = "SELECT l.user AS user_id, cup.id AS para_id, cup.name AS para_name, up.val FROM tbl_login l 
      LEFT OUTER JOIN tbl_user_para up ON up.user = l.user 
      LEFT OUTER JOIN cfg_user_para cup ON up.para = cup.id
    WHERE l.id IN (" . implode(", ", array_keys($user_list)) . ")";
    
    populate_user_dataset($user_list, 'para', db_execute($query));
  }
}

function populate_user_role(&$user_list) {
  if ($user_list) {
    $query = "SELECT ur.user AS user_id, ur.role AS role_id, ur.institute AS institute_id, r.name AS role_name, i.name AS institute_name 
      FROM tbl_user_role ur, tbl_role r, tbl_institute i
      WHERE ur.user in (" . implode(", ", array_keys($user_list)) . ") AND r.id = ur.role AND i.id = ur.institute";
   
    debug(__FILE__,__FUNCTION__,__LINE__, $query);
    populate_user_dataset($user_list, 'role', db_execute($query));
  }
}

function populate_user_contact(&$user_list) {
  if ($user_list) {
    $query = "SELECT uc.user AS user_id, c.id AS contact_id, uc.priority, c.contact, uc.type AS type_id, ct.name AS type_name 
      FROM tbl_contact c, tbl_user_contact uc, cfg_contact_type ct 
      WHERE uc.contact = c.id AND uc.user IN (" . implode(", ", array_keys($user_list)) . ") AND ct.id = uc.type";
    populate_user_dataset($user_list, 'contact', db_execute($query));
  }
}


function populate_user_last_activity_detail(&$user_list, $date){

  $result = array();

  if ($user_list) {
      foreach ($user_list as $user_data) {
      $id = $user_data['user_id'];

      $meal_1     =   "SELECT q.about AS user_id, q.id, cat.name AS type , DATE(q.max_time) AS date, TIME(q.max_time) AS meal_time,
    	tu.name AS given_by, tai1.value AS Food_type, tai2.value AS Feeding_type 
        FROM (
        SELECT ta.about, ta.type, ta.assignee, tas.id, MAX(tas.updated_ts) AS max_time
        FROM tbl_schedule_activity tas
        INNER JOIN tbl_activity ta ON ta.id=tas.activity
        INNER JOIN cfg_activity_type cat ON cat.id = ta.type
        WHERE tas.status = '3' AND LOWER(cat.name) = 'meal' AND ta.about IN ($id)  
        GROUP BY ta.about
        ) q
        LEFT OUTER JOIN tbl_activity_info tai1 ON tai1.activity = q.id
        LEFT OUTER JOIN tbl_activity_info tai2 ON tai2.activity = q.id
        INNER JOIN cfg_activity_type cat ON cat.id = q.type
		LEFT OUTER JOIN tbl_user tu ON q.assignee = tu.id
        WHERE tai1.para = (SELECT id FROM cfg_activity_para WHERE LOWER(name) = 'food_type') 
        AND tai2.para = (SELECT id FROM cfg_activity_para WHERE LOWER(name) = 'feeding_type')";

      $vital_1    =  "SELECT q.about AS user_id, q.id, cat.name AS type, date(q.max_time) AS date,
        time(q.max_time) AS vital_time, tu.name AS checked_by, a.value AS pulse, 
        b.value AS pressure, c.value AS sugar, d.value AS temperature
        FROM (
          SELECT ta.about, ta.assignee, ta.type, tas.id, MAX(tas.updated_ts) AS max_time
          FROM tbl_schedule_activity tas
          INNER JOIN tbl_activity ta ON ta.id=tas.activity
          INNER JOIN cfg_activity_type cat ON cat.id = ta.type
          WHERE tas.status = '3' AND LOWER(cat.name) = 'vital' AND ta.about IN ($id) 
          GROUP BY ta.about
          ) q
      LEFT JOIN tbl_activity_info a ON a.activity = q.id 
        AND a.para = (SELECT id FROM cfg_activity_para WHERE name = 'Pulse')
      LEFT JOIN tbl_activity_info b ON b.activity = q.id 
        AND b.para = (SELECT id FROM cfg_activity_para WHERE name = 'Pressure')
      LEFT JOIN tbl_activity_info c ON c.activity = q.id 
        AND c.para = (SELECT id FROM cfg_activity_para WHERE name = 'Sugar')
        LEFT JOIN tbl_activity_info d ON d.activity = q.id 
        AND d.para = (SELECT id FROM cfg_activity_para WHERE name = 'Temperature')
        INNER JOIN cfg_activity_type cat ON cat.id = q.type
      LEFT JOIN tbl_user tu ON tu.id = q.assignee";

 $medicine_1 =   "SELECT q.about AS user_id, q.id, cat.name AS type,date(q.max_time) AS date,
    time(q.max_time) AS medicine_time, tu.name AS given_by, 
    ti.name as medicine, tpm.unit AS unit 
    FROM (
        SELECT ta.about, ta.assignee, ta.type, tpa.id, tpa.medicine, MAX(tpa.updated_ts) AS max_time
        FROM tbl_prescription_activity tpa
        INNER JOIN tbl_activity ta ON ta.id=tpa.activity
        INNER JOIN cfg_activity_type cat ON cat.id = ta.type
        WHERE tpa.status = '3' AND LOWER(cat.name) = 'medicine' AND ta.about IN ($id) 
        GROUP BY ta.about
        ) q
    LEFT JOIN tbl_prescribed_medicine tpm ON tpm.id = q.medicine
    LEFT JOIN tbl_inventory ti ON ti.id = q.medicine
    LEFT JOIN cfg_activity_type cat ON cat.id = q.type
    LEFT JOIN tbl_user tu ON tu.id = q.assignee";

    $result = array_merge($result, db_execute($meal_1), db_execute($vital_1), db_execute($medicine_1));
    }

  populate_user_dataset($user_list, 'last_activity_detail', $result);
 }
}

function populate_user_activity_history(&$user_list){

  $result = array();
  if($user_list){
    
    $query_1 = "SELECT ta.about as user_id, cat.name as type,tu.name as given_by, ta.updated_ts 
                FROM tbl_activity ta  
                LEFT JOIN cfg_activity_type cat on cat.id = ta.type
                LEFT JOIN tbl_user tu ON tu.id = ta.assignee
                WHERE ta.about IN (" . implode(", ", array_keys($user_list)) . ") 
                AND ta.about_type = (SELECT id from cfg_assignment_level where name = 'user') 
                AND (ta.status = 3 or ta.status = 2)
              ";
  
    $query_2 = "SELECT ta.assignee as user_id, cat.name as type,tu.name as given_to, ta.updated_ts 
              FROM tbl_activity ta  
              LEFT JOIN cfg_activity_type cat on cat.id = ta.type
              LEFT JOIN tbl_user tu ON tu.id = ta.about
              where ta.assignee in (" . implode(", ", array_keys($user_list)) . ")
                AND ta.assignee_type = (SELECT id from cfg_assignment_level where name = 'user') 
                AND (ta.status = 3 or ta.status = 2)
              ";

    $result = array_merge($result, db_execute($query_1), db_execute($query_2));
    populate_user_dataset($user_list, 'activity_history', $result); 
  }
}


function populate_user_dataset(&$user_list, $element, $dataset, $is_list = true, $remove_populating_id = true) {
  populate_dataset($user_list, 'user_id', $element, $dataset, $is_list, $remove_populating_id); 
}


function get_acl_user_role_list($user_id) {
  $user_id_list = get_acl_user_list($user_id, $total, array('__no_limit__' => true));
  $user_id_csv = implode(", ", $user_id_list);
  $query = "
    SELECT DISTINCT role
    FROM tbl_user_role
    WHERE user IN ($user_id_csv)
    ";
  $db_resp = db_execute($query);
  $role_list = array_column($db_resp, 'role');
  return $role_list;  

}

function get_acl_user_institute_list($user_id) {
  $user_id_list = get_acl_user_list($user_id);
  $user_id_csv = implode(", ", $user_id_list);

  $role_list = get_acl_user_role_list($user_id);
  $role_id_csv = implode(", ", $role_list);  

  $query = "SELECT DISTINCT institute
      FROM tbl_user_role WHERE  user IN ($user_id_csv)
    UNION
     SELECT DISTINCT item AS institute 
     FROM acl_role_institute WHERE accessee IN ($role_id_csv)
    UNION
      SELECT DISTINCT item AS institute
      FROM  acl_user_institute WHERE accessee = '$user_id'
     ";
  $db_resp = db_execute($query);
  $institute_list = array_column($db_resp, 'institute');
  return $institute_list;  
}

function populate_item_list($user_id) {
  $role_list = get_acl_user_role_list($user_id);
  $institute_list = get_acl_user_institute_list($user_id);

  $query = "SELECT item as item_list
    FROM acl_user_user WHERE accessee='$user_id'
    UNION
    SELECT item as item_list
    FROM acl_role_user WHERE accessee IN (" . implode(", ", $role_list) . ") 
    UNION
    SELECT tur.user as item_list FROM acl_role_role arr
    INNER JOIN tbl_user_role tur ON tur.role=arr.item
    WHERE arr.accessee IN (" . implode(", ", $role_list) . ")
    UNION
    SELECT tur.user as item_list FROM acl_role_institute ari
    INNER JOIN tbl_user_role tur ON tur.institute=ari.item
    WHERE ari.accessee IN (" . implode(", ", $role_list) . ")
    UNION
    SELECT tur.user as item_list FROM acl_user_institute aui
    INNER JOIN tbl_user_role tur ON tur.institute=aui.item
    WHERE aui.accessee='$user_id'
    UNION
    SELECT tur.user as item_list FROM acl_user_role aur
    INNER JOIN tbl_user_role tur ON tur.role=aur.item
    WHERE aur.accessee='$user_id'
  ";
   
   debug(__FILE__,__FUNCTION__,__LINE__, $query);
   $db_resp = db_execute($query);
  $result = array_column($db_resp, 'item_list');
  if(!in_array($user_id, $result)) {
    array_push($result,$user_id);
  }
  return $result;

}
?>
