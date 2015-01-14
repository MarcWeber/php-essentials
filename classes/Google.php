<?php

class Google {

  static public function lat_lon($addr){
    $url = 'http://maps.google.com/maps/api/geocode/json?address='.urlencode($addr).'&sensor=false&region=de';
    $a = file_get_contents($url);
    $addr_info = json_decode($a, true);

    if (isset($addr_info['status']) && $addr_info['status'] == 'OVER_QUERY_LIMIT') {
      throw new Exception('google query limit exceeded');
    };

    if (!isset($addr_info['results'][0]['geometry']['location']))
      return array('lat' => null, 'lng' => null);
    $ai = $addr_info['results'][0]['geometry']['location'];
    return array('lat' => $ai['lat'], 'lng' => $ai['lng']);
  }
}
