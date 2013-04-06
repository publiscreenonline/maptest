<?php
ini_set('display_errors',1); 
 error_reporting(E_ALL);
include_once "header.php";

// Run this script after new markers have been added to the DB.
// It will look for any markers that are missing latlong values
// and automatically geocode them.

// google maps vars
define("MAPS_HOST", "maps.googleapis.com");
define("KEY", "AIzaSyB4BxUtUA2nc4eiNpeEECMCITegeFrcY9w");

// geocode all markers
geocode("places");
geocode("events");


// geocode function
function geocode($table) {
  global $hide_geocode_output;

  // get places that don't have latlong values
  $result = mysql_query("SELECT * FROM $table WHERE lat=0 OR lng=0") or die(mysql_error());

  // geocode and save them back to the db
  $delay = 0;
  $base_url = "http://" . MAPS_HOST . "/maps/api/geocode/xml?";

  // Iterate through the rows, geocoding each address
  while ($row = @mysql_fetch_assoc($result)) {
    $geocode_pending = true;

    while ($geocode_pending) {
      $address = $row["address"];
      $id = $row["id"];
      $request_url = $base_url . "&address=" . urlencode($address) . "&sensor=false";
      $xml = simplexml_load_file($request_url) or die("url not loading");

     /* echo '<pre>';
      print_r($xml);
      echo '</pre>';
      */
      $status = $xml->status;
      //echo $status;
      
      if ($status == "OK") {
          
        // Successful geocode
        $geocode_pending = false;
        $lat = $xml->result->geometry->location->lat;
        $lng = $xml->result->geometry->location->lng;
        
       //echo $lat . ", " . $lng;
        
        $query = sprintf("UPDATE $table " .
              " SET lat = '%s', lng = '%s' " .
              " WHERE id = '%s' LIMIT 1;",
              mysql_real_escape_string($lat),
              mysql_real_escape_string($lng),
              mysql_real_escape_string($id));
        $update_result = mysql_query($query);
        if (!$update_result) {
          die("Invalid query: " . mysql_error());
        }
      } else if (strcmp($status, "620") == 0) {
        // sent geocodes too fast
        $delay += 100000;
      } else {
        // failure to geocode
        $geocode_pending = false;
        //echo "Address " . $address . " failed to geocoded. ";
        //echo "Received status " . $status . " \n";
      }
      usleep($delay);
    }
  }

  // finish
  if(@$hide_geocode_output != true) {
    echo mysql_num_rows($result)." $table geocoded<br />";
  }

}

?>
