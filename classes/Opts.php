<?php

trait Opts {
  public function __get($k){
    if (array_key_exists($k, $this->opts))
      return $this->opts[$k];
    throw new Exception('unkown property '.$k);
  }

  public function opts_replace($opts){
    return array_replace($this->opts, $opts);
  }
}
