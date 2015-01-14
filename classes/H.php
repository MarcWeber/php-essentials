<?php

// helper methods
class H {

  static public function get_or(array $a, $k, $d = null){
    return array_key_exists($k, $a) ? $a[$k] : $d;
  }

  static public function assert_not_null($x){
    if (!is_null($x))
      throw new Exception('thing is null');
  }

  static public function redirect_exit($url){
    // Weiterleitung zur geschützten Startseite
    if ($_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1') {
      if (php_sapi_name() == 'cgi') {
        header('Status: 303 See Other');
      }
      else {
        header('HTTP/1.1 303 See Other');
      }
    }
    header('Location: '.$url);
    exit();
  }

  static public function url_params($gets){
    return (count($gets) == 0)
    ? ''
    : '?'.implode('&', array_map(function($k)use(&$gets){return $k.'='.urlencode($gets[$k]);}, array_keys($gets)));
  }


  static public function esc_for_regex($s){
    return preg_replace('/[\\/]/','\\/', $s);
  }

  static public function className($o){
    $c = new ReflectionClass($o);
    return $c->getName();
  }

  static public function js($js){
    return '<script type="text/javascript">'.$js.'</script>';
  }

  // with some protection - probably it is hackable, but should take some
  // effort and dedication
  static public function serialize($o){
    $s = base64_encode(serialize($o));
    return md5($s.SERIALIZE_SALT).$s;
  }
  static public function unserialize($s){
    $l = strlen(md5(''));
    $code = substr($s, 0, $l);
    $rest = substr($s, $l);
    if ($code != md5($rest.SERIALIZE_SALT))
      throw new Exception('bad');
    return unserialize(base64_decode($rest));
  }

  static public function assert($b, $msg = ""){
    if (!$b) throw new Exception( 'assertion failed' . $msg );
  }

  static public function replace_all($s, $ar){
    foreach ($ar as $k => $v) {
      $s = str_replace($k, $v, $s);
    }
    return $s;
  }

  static public function defined_and_true($s){
    return defined($s) && constant($s) === true;
  }

}
