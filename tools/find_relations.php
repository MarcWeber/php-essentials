<?php

// this code compares all int fields among all tables
// if valuse look like beeing a 1:n relation the combination is output.
// may run quite a while and or a higher memory limit
ob_end_flush();
ini_set('max_execution_time', 99999999);

require_once("include/init.php");

$tables = $db->queryFirstCol('show tables');

var_dump($tables);

var_dump($db->queryAllRows('describe '.$tables[0]));

$fs = array();
foreach ($tables as $t) {
  $fields = $db->queryAllRows('describe '.$t);
  foreach ($fields as &$t1_f) {
     if (substr($t1_f['Type'],0,3) == 'int'){
       $t1_f['values'] = $db->queryFirstCol('SELECT distinct '.$t1_f['Field'].' FROM '.$t);
       $t1_f['count'] = $db->queryOneValue('SELECT count(*) FROM '.$t);
     }
  }unset($t1_f);
  $fs[] = $fields;
}


for ($i = 0; $i < count($tables); $i++) {
  $t1 = $tables[$i];
  for ($j = $i+1; $j < count($tables); $j++) {
    $t2= $tables[$j];
    echo "comparing $t1 with $t2\n";
    flush();

    foreach ($fs[$i] as $t1_f) {
      foreach ($fs[$j] as $t2_f) {
        if ('int' == substr($t1_f['Type'], 0, 3) and 'int' == substr($t2_f['Type'], 0, 3)){
          // same type?
          $v1_nub = $t1_f['values'];
          $v2_nub = $t2_f['values'];
          $v1_count = $t1_f['count'];
          $v2_count = $t2_f['count'];

          if ($v1_count == count($v1_nub)){
            // 1 is master
            $master = array($t1, $t1_f['Field']);
            $detail = array($t2, $t2_f['Field']);
            $master_ints = $v1_nub;
            $detail_ints = $v2_nub;
          } elseif ($v2_count == count($v2_nub)) {
            // 2 is master?
            $master = array($t2, $t2_f['Field']);
            $detail = array($t1, $t1_f['Field']);
            $master_ints = $v2_nub;
            $detail_ints = $v1_nub;
          } else
            continue;

          $no_master_id = array_unique(array_diff($detail_ints, $master_ints));

          if (count($no_master_id) / count($detail_ints) < 0.01 )
            echo $master[0].'.'.$master[1].' 1:n '
            . $detail[0].'.'.$detail[1]."\n";
        }
        flush();
        $values1 = null;
        $values2 = null;
      }
    }
  }
}
