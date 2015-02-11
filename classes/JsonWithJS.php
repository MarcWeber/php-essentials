<?php

// js formatter with "verbatim js"
class JsonWithJS {

  public function __construct($js){
    $this->js = $js;
  }

  static public function js($thing){
    if (is_string($thing)){
      return json_encode($thing);
    } elseif (is_bool($thing)){
      return $thing ? 'true' : 'false';
    } elseif (is_float($thing)){
      return $thing.'';
    } elseif (is_int($thing)){
      return $thing.'';
    } elseif (is_array($thing)){
      if (serialize(array_keys($thing)) == serialize(array_keys(array_values($thing)))){
        // regular array without keys
        return '['.implode(',', array_map('JsonWithJS::js', $thing)).']';
      } else {

        return '{'.implode(',', 
            array_map(function($k)use(&$thing){
              return json_encode($k).': '.JsonWithJS::js($thing[$k]);
            }, array_keys($thing)))
          .'}';
        // keys are not 0, 1, ... thus, encode as dictionary
      }
    } elseif ($thing instanceof JsonWithJS) {
      return $thing->js;
    } else {
      throw new Exception('unexpected '.var_export($thing, true));
    }
  }

  static public function call_method($name, ...$args){
    return "$name(".implode(',', array_map(function($x){return self::js($x);}, $args)).");";
  }
}
