<?php

class FileCache {

  function __construct($opts){
    A::assert_key($opts, 'cache_directory');
    $this->opts = $opts;
  }

  public function file($key){
    return $this->opts['cache_directory'].'/'.md5($key);
  }

  public function get($key, $d){
    $file = $this->file($key);
    if (file_exists($file)){
      return unserialize(file_get_contents($file))['value'];
    }
  }
  public function get_or_evaluate($key, $f, $ttl = null){
    $file = $this->file($key);
    if (file_exists($file)){
      $o = unserialize(file_get_contents($file));
      if (array_key_exists('ttl', $o) && $o['ttl'] >= time())
        return $o['value'];
    }
    $v = call_user_func($f);
    $this->set($key, $v, $ttl);
    return $v;
  }

  public function set($key, $v, $ttl = null){
    $file = $this->file($key);
    $tmp = $file.rand(1000, 2000);
    // store in array so that additional information such as TTL could be added
    $o = ['value' => $v];
    if (!is_null($ttl)) {
      $o['ttl'] = time() + $ttl;
    }
    file_put_contents($tmp, serialize($o));
    rename($tmp, $file); // atomic operation
  }

}
