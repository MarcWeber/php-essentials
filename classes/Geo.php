<?php 

class Geo {

  static public function geoplugin_net($ip = null){
    if (is_null($ip)) {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    global $cache_in_memory;
    return $cache_in_memory->get_or_evaluate('geo_plugin_net_'.$ip, function()use($ip){
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, 'http://www.geoplugin.net/php.gp?ip='.$ip);
      curl_setopt($ch, CURLOPT_PORT, 80);
      curl_setopt($ch, CURLOPT_VERBOSE, 0);

      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
      curl_setopt($ch, CURLOPT_TIMEOUT, 2);

      curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
      curl_setopt($ch, CURLOPT_POST, 1);

      $response = curl_exec($ch);
      $curl_err = curl_errno($ch);
      if ($curl_err){
        $curl_message = curl_error($ch);
        curl_close($ch);
        throw new Exception($curl_err.' '.$curl_message);
      }
      H::assert(S::starts_with($response, 'a:18')); // at least ensure its an array
      return unserialize($response);
    }, 60 * 60 * 5);
  }

  static public function lat_lon_distance_km($a, $b) {
    $lat1 = $a['lat'];
    $lon1 = $a['lng'];
    $lat2 = $b['lat'];
    $lon2 = $b['lng'];

    $theta = $lon1 - $lon2; 
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
    $dist = acos($dist); 
    $dist = rad2deg($dist); 
    return $dist * 60 * 1.85316;
  }
}
