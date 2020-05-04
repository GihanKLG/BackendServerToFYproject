<?php
require_once 'db_user.php';

function db_read_location($args) {

  //http://localhost/googlemap/svr/report.php?action=read&session_id=ss9h138m6eptg7g4ffgn5p5511
   $query = "SELECT lat, lng, Village AS Division 
    FROM tmp_artisanal_mining_full
    UNION
    SELECT lat, lng, gsdivision AS Division 
    FROM tmp_kaluthara_iml_c
    -- UNION
    -- SELECT lat, lng, Cu AS cubes
    -- FROM tmp_ro_al_and_iml";

    $result = db_execute($query);

    succ_return(array(
    'Location' => $result,
    ));
}

//http://localhost/googlemap/svr/report.php?action=division_read&session_id=ss9h138m6eptg7g4ffgn5p5511
function db_read_division($args) {
  $query = "SELECT DISTINCT Village AS reagion
  FROM tmp_artisanal_mining_full
  UNION
  SELECT DISTINCT D.S.Division AS reagion
  FROM tmp_kaluthara_iml_c";
  $query = "SELECT GN AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    FROM tmp_artisanal_mining_full
    GROUP BY Division
    UNION 
    SELECT gsdivision AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    FROM tmp_kaluthara_iml_c
    GROUP BY Division
    -- UNION
    -- SELECT gs AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    -- FROM tmp_ro_al_and_iml
    -- GROUP BY Division";

  $result = db_execute($query);
  $length = sizeof($result);
  // $points = array();
  // $coord = array(3.1415926, 57.29578);
  // $min = 100;
  $sum = 0;

  for($i=0;$i<$length;$i++) {
    $sum = $sum + $result[$i]['count'];
    $min = 100000000000;
    if($result[$i]['count'] > 2) {
      //debug(__FILE__, __FUNCTION__, __LINE__, $result[$i]['count']);
      $size = $result[$i]['count'];
      $lat = $result[$i]['lat'];
      $lat = explode(',', $lat);
      $lng = $result[$i]['lng'];
      $lng = explode(',', $lng);
      $arr_length = sizeof($lat);
      $points = array();
      //debug(__FILE__, __FUNCTION__, __LINE__, $arr_length);
      for($j=0;$j<$arr_length;$j++) {
        // $point1['lat'] = $lat[$j];
        // $point1['long'] = $lng[$j];
        $point1 = array();
        // $point1.push($lat[$j]);
        // $point1.push($lng[$j]);
        $point1['0'] = $lat[$j];
        $point1['1'] = $lng[$j];
        //debug(__FILE__, __FUNCTION__, __LINE__, $point1);
        array_push($points, $point1);
      }
      // if($result[$i]['count'] == 161)
      // debug(__FILE__, __FUNCTION__, __LINE__, $points);
  //debug(__FILE__, __FUNCTION__, __LINE__, $points);
  $points_length = sizeof($points);
  $min_dist = array();
  for($k=0;$k<$points_length;$k++) {
      $coord = $points[$k];
      // if($result[$i]['count'] == 161)  
     // debug(__FILE__, __FUNCTION__, __LINE__, $coord);  
      $closestPoint = $closestDistance= false;;

      foreach($points as $point) {
        list($x,$y) = $point;

        // Not compared yet, use first poit as closest
        if($closestDistance === false) {
            $closestPoint = $point;
            if($x != $coord[0] && $y != $coord[1])
            $closestDistance = distance($x,$y,$coord[0],$coord[1]);
            // if($result[$i]['count'] == 161)
            //debug(__FILE__, __FUNCTION__, __LINE__, $x, $y, $closestDistance);
            continue;
        }

        // If distance in any direction (x/y/z) is bigger than closest distance so far: skip point
        if(abs($coord[0] - $x) > $closestDistance) continue;
        if(abs($coord[1] - $y) > $closestDistance) continue;
    
        $newDistance = distance($x,$y,$coord[0],$coord[1]);
        // if($result[$i]['count'] == 161)
        // debug(__FILE__, __FUNCTION__, __LINE__, $x, $y, $newDistance);

        if($newDistance < $closestDistance) {
            $closestPoint = $point;
            if($x != $coord[0] || $y != $coord[1])
            $closestDistance = distance($x,$y,$coord[0],$coord[1]);
        }  
      //  debug(__FILE__, __FUNCTION__, __LINE__, $closestDistance);     
    }

    // if($closestDistance < $min && $closestDistance != 0) {
    // $min = $closestDistance;  
    // $result[$i]['min_distance'] = $min;
    // }
    // var_dump($closestPoint);
    if($closestDistance > 10) $closestDistance = 10; 
    $min_dist[$k] = $closestDistance;
    // if($result[$i]['count'] == 161)
    //debug(__FILE__, __FUNCTION__, __LINE__, $k, $coord, $closestDistance);
  }
  // if($min == 100000000000) {
  //   debug(__FILE__, __FUNCTION__, __LINE__, "error");
  //   $result[$i]['min_distance'] = 10;
  // }
  $result[$i]['min_distance'] = $min_dist;
  //if($result[$i]['min_distance'] > 10) $result[$i]['min_distance'] = 10;
  if($result[$i]['count'] == 161)
  debug(__FILE__, __FUNCTION__, __LINE__, $result[$i]['min_distance']);
  
  }
  else
  $result[$i]['min_distance'] = array(10);
} 

  // debug(__FILE__, __FUNCTION__, __LINE__, $sum);
  succ_return(array(
    'Location' => $result,
    ));
}

// function distance($x1,$y1,$x2,$y2) {
//   return sqrt(pow($x1-$x2,2) + pow($y1 - $y2,2));
// }

// function distance($x1,$y1,$x2,$y2){

//   $radius      = 3958;      // Earth's radius (miles)
//   $pi          = 3.1415926;
//   $deg_per_rad = 57.29578;  // Number of degrees/radian (for conversion)

//   $point1['lat'] = $x1;
//   $point1['long'] = $y1;
//   $point2['lat'] = $x2;
//   $point2['long'] = $y2;
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

function distance($lat1, $lon1, $lat2, $lon2) {

  $pi80 = M_PI / 180;
  $lat1 *= $pi80;
  $lon1 *= $pi80;
  $lat2 *= $pi80;
  $lon2 *= $pi80;

  $r = 6372.797; // mean radius of Earth in km
  $dlat = $lat2 - $lat1;
  $dlon = $lon2 - $lon1;
  $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlon / 2) * sin($dlon / 2);
  $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
  $km = ($r * $c)*500;

  //echo '<br/>'.$km;
  return $km;
}

//http://localhost/googlemap/svr/report.php?action=district_count&session_id=ss9h138m6eptg7g4ffgn5p5511
function db_read_district_count($args) {
  $query = "SELECT count(seq) AS count, District
  FROM tmp_artisanal_mining_full
  GROUP BY District
  UNION
  SELECT count(NO) AS count, district
  FROM tmp_ro_al_and_iml
  GROUP BY district
  UNION
  SELECT count(NO) AS count, District
  FROM tmp_kaluthara_iml_c
  GROUP BY District";

 $result = db_execute($query);

succ_return(array(
  'district_count' => $result,
  ));

}  
?>