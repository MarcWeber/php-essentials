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

  static public function group_by($array, $key){
    $r = array();
    foreach( $array as $a){
      $r[$a[$key]][] = $a;
    }
    return $r;
  }
   public static function build($set){
     $subset = array_shift($set);
     $cartesianSubset = self::build($set);

     $result = array();
     foreach ($subset as $value) {
       foreach ($cartesianSubset as $p) {
         array_unshift($p, $value);
         $result[] = $p;
       }
     }
     return $result;
   }

  static public function tree_by_parent_key($arr, $k_parent_key, $k_key){
    $by_key = [];
    $roots_by_key = [];

    foreach ($arr as &$a) {
      A::ensure($a, 'children', []);
      $by_key[$a[$k_key]] =& $a;
    } unset($a);

    foreach ($arr as &$a) {
      $pk = $a[$k_parent_key];
      if (array_key_exists($pk, $roots_by_key)) {
        $roots_by_key[$pk]['children'][] =& $a;
      } else {
        foreach (array_keys($roots_by_key) as $k) {
          if ($a[$k_key] == ($roots_by_key[$k][$k_parent_key])){
            $a['children'][] =& $roots_by_key[$k];
            unset($roots_by_key[$k]);
          }
        }
        $roots_by_key[$a[$k_key]] =& $a;
      }
    } unset($a);

    return $roots_by_key;
  }

}
