<?php
require_once 'db_user.php';

function db_read_location($args) {

  //http://localhost/googlemap/svr/report.php?action=read&session_id=ss9h138m6eptg7g4ffgn5p5511
   $query = "SELECT lat, lng, Cubes AS cubes 
    FROM tmp_artisanal_mining_full
    UNION
    SELECT lat, lng, Cubes AS cubes 
    FROM tmp_kaluthara_iml_c
    UNION
    SELECT lat, lng, Cu AS cubes
    FROM tmp_ro_al_and_iml";

    $result = db_execute($query);

    succ_return(array(
    'Location' => $result,
    ));
}

function db_read_division($args) {
  // $query = "SELECT DISTINCT Village AS reagion
  // FROM tmp_artisanal_mining_full
  // UNION
  // SELECT DISTINCT D.S.Division AS reagion
  // FROM tmp_kaluthara_iml_c";
  $query = "SELECT Village AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    FROM tmp_artisanal_mining_full
    GROUP BY Division";

  $result = db_execute($query);

  succ_return(array(
    'Location' => $result,
    ));
}

// function getDistance($point1, $point2){

//   $radius      = 3958;      // Earth's radius (miles)
//   $pi          = 3.1415926;
//   $deg_per_rad = 57.29578;  // Number of degrees/radian (for conversion)

//   $distance = ($radius * $pi * sqrt(
//               ($point1['lat'] - $point2['lat'])
//               * ($point1['lat'] - $point2['lat'])
//               + cos($point1['lat'] / $deg_per_rad)  // Convert these to
//               * cos($point2['lat'] / $deg_per_rad)  // radians for cos()
//               * ($point1['long'] - $point2['long'])
//               * ($point1['long'] - $point2['long'])
//       ) / 180);

//   $distance = round($distance,1);
//   return $distance;  // Returned using the units used for $radius.
// }
  
?>