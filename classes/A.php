<?php

// array related helpers
class A {

  static public function get_or(array $a, $k, $d = null){
    return array_key_exists($k, $a) ? $a[$k] : $d;
  }

  static public function first_key($a, $keys, $default_pair = [null, null]){
    foreach ($keys as $k) {
      if (array_key_exists($k, $a))
        return [$k, $a[$k]];
    }
    return $default_pair;
  }

  static public function assert_key(array &$a, $k){
    if (!array_key_exists($k, $a))
      throw new Exception('array does not contain key '.$k);
  }

  static public function ensure(array &$a, $k, $v){
    if (!array_key_exists($k, $a))
      $a[$k] = $v;
  }

  static public function push(&$a, $key, $v){
    A::ensure($a, $key, []);
    $a[$key][] = $v;
  }

  static public function push_unique(&$a, $key, $v){
    A::ensure($a, $key, []);
    if (!in_array($v, $a[$key])) {
      $a[$key][] = $v;
    }
  }
}
