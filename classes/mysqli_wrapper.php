<?php
// or use mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)

class mysqli_wrapper {
  function __construct($mysqli){
    $this->mysqli = $mysqli;
  }

  public function __call($method, $args){
    $r = call_user_func_array([$this->mysqli, $method], $args);
    if (in_array($method, ['prepare']) && $r === false)
      throw new Exception($this->mysqli->error);

    if ($method == 'prepare')
      $r = new mysqli_stmt_wrapper($r);
    return $r;
  }
}

class mysqli_stmt_wrapper {
  function __construct($stmt){
    $this->stmt = $stmt;
  }

  public function execute(...$args){
    $r = call_user_func_array([$this->stmt, $method], $args);
    if (in_array($method, ['execute']) && $r === false)
      throw new Exception($this->mysqli->error);

    return $r;
  }
}

