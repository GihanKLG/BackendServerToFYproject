<?php
require_once 'db_user.php';
require_once 'db_provience.php';


//http://localhost/googlemap/svr/report.php?action=read&location=LatLng(5.959917,%2080.601349)&session_id=123456
function db_read_location($args) {

   $location = $args['location'];
   $size = strlen($location);
   $location = substr($location, 7, $size-8);
   $current_location = explode(', ', $location);
   $longitude_x = $current_location[1];
   $latitude_y = $current_location[0];

   $province = db_find_provience($longitude_x, $latitude_y);
   debug(__FILE__, __FUNCTION__, __LINE__, $province);

   $query = "SELECT lat, lng, Village AS Division
    FROM tmp_artisanal_mining_full
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
    UNION
    SELECT lat, lng, gsdivision AS Division 
    FROM tmp_kaluthara_iml_c
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
    UNION
    SELECT lat, lng, gs AS Division
    FROM tmp_ro_al_and_iml
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30";

    debug(__FILE__, __FUNCTION__, __LINE__, $query);

    $result = db_execute($query);
    $min_dist = distance($current_location[0], $current_location[1], $result[1]['lat'], $result[1]['lng']);
    $size = sizeof($result);
    for($i=0;$i<$size;$i++) {
      $d = distance($current_location[0], $current_location[1], $result[$i]['lat'], $result[$i]['lng']);
      if($min_dist > $d) {
        $min_dist = $d;
        $min_div = $result[$i]['Division'];
        $min_lat = $result[$i]['lat'];
        $min_lng = $result[$i]['lng'];
      }  
      //debug(__FILE__, __FUNCTION__, __LINE__, $min_dist);
    }
    debug(__FILE__, __FUNCTION__, __LINE__, $min_lat, $min_lng, $min_div);
    $nearest['lat'] = $min_lat;
    $nearest['lng'] = $min_lng;

    $query = "SELECT lat, lng, Village AS Division 
      FROM tmp_artisanal_mining_full
      WHERE Village = '$min_div'
      AND 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
      POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
      UNION
      SELECT lat, lng, gsdivision AS Division 
      FROM tmp_kaluthara_iml_c
      WHERE gsdivision = '$min_div'
      AND 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
      POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
      UNION
      SELECT lat, lng, gs AS Division
      FROM tmp_ro_al_and_iml
      WHERE gs = '$min_div'
      AND 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
      POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30";

    $result1 = db_execute($query);
    debug(__FILE__, __FUNCTION__, __LINE__, $query);

    $size = sizeof($result1);
    debug(__FILE__, __FUNCTION__, __LINE__, $result1);

    $radius = 100000000000000;
    for($i=0;$i<$size;$i++) {
      $distance = distance($min_lat, $min_lng, $result1[$i]['lat'], $result1[$i]['lng']);
      // debug(__FILE__, __FUNCTION__, __LINE__, $min_lat, $min_lng, $result1[$i]['lat'], $result1[$i]['lng']);
      debug(__FILE__, __FUNCTION__, __LINE__, $distance);
      if($distance < $radius && $distance != 0) {
        $radius = $distance;
      }
      //debug(__FILE__, __FUNCTION__, __LINE__, $result[$i]['Division']);
    }
    if($radius > 10) $radius = 10;
    $nearest['radius'] = $radius/1000;
    $n_distance = distance($current_location[0], $current_location[1], $min_lat, $min_lng);
    $nearest['distance'] = $n_distance/500;
    $nearest['division'] = $min_div;

    succ_return(array(
    'Location' => $result,
    'nearest_place' => $nearest,
    'province' => $province
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
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
    GROUP BY Division
    UNION 
    SELECT gsdivision AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    FROM tmp_kaluthara_iml_c
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
    GROUP BY Division
    UNION
    SELECT gs AS Division, GROUP_CONCAT(lat) AS lat, GROUP_CONCAT(lng) AS lng, count(lat) AS count
    FROM tmp_ro_al_and_iml
    WHERE 3956 * 2 * ASIN(SQRT( POWER(SIN(($latitude_y - lat) * pi()/180 / 2), 2) + COS($latitude_y * pi()/180) * COS(lat * pi()/180) *
    POWER(SIN(($longitude_x - lng) * pi()/180 / 2), 2) )) <= 30
    GROUP BY Division";

  $result = db_execute($query);
  $length = sizeof($result);
  $sum = 0;

  for($i=0;$i<$length;$i++) {
    $sum = $sum + $result[$i]['count'];
    $min = 100000000000;
    if($result[$i]['count'] >= 2) {
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
 
function distance($lat1, $lon1, $lat2, $lon2) {
  if (($lat1 == $lat2) && ($lon1 == $lon2)) {
    return 0;
  }
  else {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;

    return ($miles * 1.609344*500);
  }
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