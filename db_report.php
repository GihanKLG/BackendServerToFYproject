<?php
require_once 'db_user.php';

function db_read_location($args) {

  //http://localhost/googlemap/svr/report.php?action=read&session_id=ss9h138m6eptg7g4ffgn5p5511
   $query = "SELECT lat, lng 
    FROM tmp_artisanal_mining_full
    UNION
    SELECT lat, lng 
    FROM tmp_kaluthara_iml_c
    UNION
    SELECT lat, lng
    FROM tmp_ro_al_and_iml";

    $result = db_execute($query);

    succ_return(array(
    'Location' => $result,
    ));
}
  
?>