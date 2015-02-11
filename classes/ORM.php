<?php

/* minimalistic orm library


  usage:

  class F {
    const TABLE_NAME = "foo";
    ..
  }

  $f = new F(array('field' => 'value'));
  $f->insert();

  or new F(id)

  Its main purpose is to put table specific methods into a defined location
*/

class ORM {

  public $row;

  protected $loaded;

  public function __construct($row_or_id, $opts = array()){
    $scheme_table = $this->scheme_table();
    $pks = d($scheme_table, 'primaryKeyFields', array());

    if (is_array($row_or_id)){
      $this->row = $row_or_id;
      $this->loaded =
        (0 == count($pks) || isset($row_or_id[$this->PRIMARY_KEY_FIELD()]))
        ? $row_or_id
        : array(); // no id, thus assume nothing was loaded from database yet
    } else {
      global $db;
      $this->row = $db->queryGenauEineZeile(
             'SELECT * FROM ?n WHERE ?n = ?i'
            .(d($opts, 'for_update', false) ? ' FOR UPDATE' : '')
        ,$this->TABLE_NAME(), $this->PRIMARY_KEY_FIELD(), $row_or_id);
      $this->loaded = $this->row;
    }
  }

  static public function instance_from_array(array $row, $c){
    if (is_null($c)) { $c = get_called_class(); }
    return new $c($row);
  }
  static public function instances_from_array(array $rows, $c){
    if (is_null($c)) { $c = get_called_class(); }
    return array_map(function($row)use($c){return new $c($row); }, $rows);
  }
  static public function query(){
    global $db;
    $args = func_get_args();
    return self::instances_from_array(call_user_func_array(array($db, 'query'), $args), get_called_class());
  }
  static public function queryOne(){
    global $db;
    $args = func_get_args();
    $c = get_called_class();
    return new $c(call_user_func_array(array($db, 'queryGenauEineZeile'), $args));
  }

  public function image_url($field, $size){
    return BBG::BBG2_URL($this->TABLE_NAME(), json_decode($this->row[$field], true), $size);
  }
  public function image_img_tag($field, $size){
    return BBG2::BBG2_IMG($this->TABLE_NAME(), json_decode($this->row['bild'], true), $size);
  }
  public function image_from_post($field, $file_from_post){
    $name = $file_from_post['name'];
    $size = BBG2::bild_von_post($file_from_post, $this->TABLE_NAME(), $name, '=');
    $size['name'] = $name;
    $this->row[$field] = json_encode($size);
  }

  public function fieldChanged($field){
    return !isset($this->loaded[$field]) || $this->loaded[$field] !== $this->row[$field];
  }

  public function changedFields(){
    $r = array();
    foreach ($this->row as $key => $value) {
      if ($this->fieldChanged($key))
        $r[] = $key;
    }
    return $r;
  }

  public function replace($row){
    $this->row = array_replace($this->row, $row);
  }

  public function update(){
    $this->check();
    global $db;
    // only store fields which have changed:
    $update = array();
    foreach ($this->row as $key => $value) {
      if ($this->fieldChanged($key))
        $update[$key] = $this->row[$key];
    }

    if (!empty($update)) {
      $db->autoUpdate($this->TABLE_NAME(), $update, $this->keys());
    }
  }

  public function insert(){
    $this->check();
    global $db;
    $id = $db->autoInsert($this->TABLE_NAME(), $this->row);
    $this->row[$this->PRIMARY_KEY_FIELD()] = $id;
    $this->loaded = $this->row;
    return $id;
  }

  public function reflectionClass(){
    static $r;
    if (is_null($r)) {
      $r = new ReflectionClass($this);
    }
    return $r;
  }

  public function check(){
    $table = Scheme::tableByName($this->TABLE_NAME($this->TABLE_NAME()));
    // decimal German users turn 10,10 into 10.10 (database friendly)
    foreach ($table['fields'] as $field) {
      if (isset($this->row[$field['name']])){
        if (preg_match('/decimal/', $field['type'])) {
          $this->row[$field['name']] = str_replace(',', '.', $this->row[$field['name']]);
        }
      }
    }
  }

  public function TABLE_DESCRIPTION(){
    $tc = null;
    if (is_null($tc)) {
      $tables = TableFields::tables();
      $tc =& $tables[$this->TABLE_NAME()];
    }
    return $tc;
  }

  function parent_fieldname_of_childtable($table){
    $table = Scheme::tableByName($table);
    foreach ($table['fields'] as $field) {
      if (isset($field['references']) && $field['references']['table'] == $this->TABLE_NAME()) {
        return $field['name'];
      }
    }
    return null;
  }

  function __call($funcname, $args = array()) {
    $r = $this->reflectionClass();

    // child relations, return childs as objects
    if (preg_match('/childs_(.*)/', $funcname, $m)){
      $p_f = $this->parent_fieldname_of_childtable($m[1]);
      return call_user_func_array($m[1].'Row::query', array('SELECT * FROM ?n WHERE ?n = ?i', $m[1], $p_f, $this->row[$this->PRIMARY_KEY_FIELD()]));
    }

    // child relations, return childs as objects
    if (preg_match('/ensure_child_(.*)/', $funcname, $m)){
      $child_data = $args[0];
      $table = Scheme::tableByName($m[1]);
      foreach ($table['fields'] as $field) {
        if (isset($field['references']) && $field['references']['table'] == $this->TABLE_NAME()) {
          $child_data[$field['name']] = $this->key();
          return call_user_func($table['name'].'Row::ensure', $child_data);
        }
      }
    }

    // parent_field -> return orm of referenced table
    if (preg_match('/^parent_(.*)/', $funcname, $m)){
      $field = Scheme::fieldByName($this->TABLE_NAME(), $m[1]);
      my_assert($field !== null);
      $ormClass = $field['references']['table'].'Row';
      return new $ormClass($this->row[$m[1]]);
    }

    $a = $r->getConstants();
    if ($funcname == "TABLE_NAME") {
      $clazz = get_called_class();
      return self::table_from_classname($clazz);
    }

    if (isset($a, $funcname))
      return $a[$funcname];

    throw new Exception('bad function call '.$funcname);
  }


  static public function table_from_classname($clazz){
    return preg_replace('/Row$/', '', $clazz);
  }

  public function __get($x){
    H::assert(array_key_exists($x, $this->row), "{$x} not in row ".var_export($this->row, true));
    return $this->row[$x];
  }

  public function __set($x, $n){
    $table = Scheme::tableByName($this->TABLE_NAME());
    foreach ($table['fields'] as &$field) {
      if ($field['name'] == $x){
        $this->row[$x] = $n;
        return;
      }
    } unset($field);
    throw new Exception('no database field :'.$x);
  }

  public function reload(){
    global $db;
    $this->row = $db->queryGenauEineZeile('SELECT * FROM ?n WHERE ?v', $this->TABLE_NAME(), $db->whereAll($this->keys()));
  }

  public function scheme_table(){
    $tn = $this->TABLE_NAME();
    return Scheme::tableByName($tn);
  }

  public function key(){
    $scheme_table = $this->scheme_table();
    return $this->row[$scheme_table['primaryKeyFields'][0]];
  }

  // return key fields and values as array
  public function keys(){
    $scheme_table = $this->scheme_table();
    return Arr::copyKeys($scheme_table['primaryKeyFields'], $this->row);
  }
  public function key_(){
    return $this->row[$this->PRIMARY_KEY_FIELD()];
  }

  public function PRIMARY_KEY_FIELD(){
    $scheme_table = $this->scheme_table();
    $pks = $scheme_table['primaryKeyFields'];
    if (count($pks) != 1)
      throw new Exception('cannot return primary key field, there are 0 ore more than 1');
    return $pks[0];
  }

  // ensure this data item exists
  public static function ensure($data){
    global $db;
    try {
      return self::queryOne('SELECT * FROM ?n WHERE ?v',
        self::table_from_classname(get_called_class()),
        $db->whereAll($data)
      );
    } catch (DBFalscheZeilenzahl $e) {
      $c = self::instance_from_array($data, get_called_class());
      $c->insert();
      return $c;
    }
  }

  public function loadChildsAsArrays($childs_and_constraints){
    global $db;
    foreach ($childs_and_constraints as $child_table => $constraint){
      $this->row[$child_table] = $db->query('
        SELECT * 
        FROM ?n 
        WHERE ?n = ?i AND ?v',
        $child_table,
        $this->parent_fieldname_of_childtable($child_table),
        $this->key(),
        new Verbatim($constraint));
    }
  }

}

