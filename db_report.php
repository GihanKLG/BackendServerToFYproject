<?php
require_once 'db_user.php';

function db_read_location($args) {

  //http://localhost/googlemap/svr/report.php?action=read&session_id=ss9h138m6eptg7g4ffgn5p5511
   $query = "SELECT lat AS lat, lang AS lng
    FROM tmp_artisanal_mining_full";

    $result = db_execute($query);

    succ_return(array(
    'Location' => $result,
    ));
}
  
?>