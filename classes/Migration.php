<?php

/*

port of github.com/MarcWeber/haxe-db-scheme

description (EN):
Each time you modify the database scheme a new migration file will be written 
updating your database. Review that file, then remove the exception line.

description(GER):
Jedesmal, wenn sie die Datenbankbeschreibung aktualisieren, wird eine neue 
Migrationsdatei angelegt. Kontrollieren Sie diese Datei, dann entfernen sie die Exception 
Zeile

Example:

  $scheme = array(
    'tables' => array(

            // version table
            array(
              'name' => 'version',
              'fields' => array(
                  array('name' => 'version', 'type' => 'int(10)')
              )
            ),

            array(
              'name' => 'brand',
              'fields' => array(
                array('name' => 'brand_id', 'type' => 'int(10)'),
                array('name' => 'name', 'type' => 'varchar(150)'),
              )
            ),

            // navigation: Kategorie, Subkategorie
            array(
               'name' =>  "products",
               'fields' => array(
                array('name' => "prod_id", 'type' => 'int(10)'),
                array('name' => "brand_id", 'type' => 'varchar(150)', 'references' => array('table' => 'brands', 'field' => 'brand_id')),
              )
            ),
    );
  );

  if (isset($_GET['migrate_db'])){
    $migration_obj = new Migration(LIB.'/migrations', $scheme);
    $migration_obj->migrate();
  }
*/

# dropping placeholder in { .. }, you should have a mapping such as inoremap <s-cr> <esc>o to get there
if (!function_exists('match_first')) {
  function match_first($a, $s){
    foreach ($a as $key => $f) {
      if (preg_match($key, $s, $m)){
        return $f($m);
      }
    }
    throw new Exception('nothing matched for '.$s);
  }
}

# dropping placeholder in { .. }, you should have a mapping such as inoremap <s-cr> <esc>o to get there
# dropping placeholder in { .. }, you should have a mapping such as inoremap <s-cr> <esc>o to get there
if (!function_exists('both_left_right')) {
    function both_left_right(array $a, array $b) {
      $b_ = $b;
      $left = array();
      $both = array();
      foreach ($a as &$x) {
        if (false !== ($key = array_search($x, $b_))){
          $both[] = $x;
          unset($b_[$key]);
        } else $left[] = $x;
      } unset($x);
      return array( 'left' =>  $left, 'both' =>  $both, 'right' =>  $b_);
    }
}


/* field types
  varchar( length: Int ); // assuming String is UTF-8
  bool; // always stored enum('y','n')
  // tinyint
  // smallint
  // mediumint
  int( length: Int, ?signed: Bool, ?zerofill: Bool);
  // TINYINT[(length)] [UNSIGNED] [ZEROFILL]
  // SMALLINT[(length)] [UNSIGNED] [ZEROFILL]
  // MEDIUMINT[(length)] [UNSIGNED] [ZEROFILL]
  // INT[(length)] [UNSIGNED] [ZEROFILL]
  // INTEGER[(length)] [UNSIGNED] [ZEROFILL]
  // BIGINT[(length)] [UNSIGNED] [ZEROFILL]
  
  // db_enum( valid_items: Array<String> );
  datetime;
  date;
  time;
  timestamp;
  text; // text field. arbitrary length. Maybe no indexing and slow searching
*/

class DBMySQLScheme extends DBScheme{

  static public function sql_field($field, $include_references) {
    $t = match_first(array(
      '/^bool$/' => function ($m) { return "enum('Y','N')"; },
      '/^int\(([^)]*)\)$/' => function ($m) { 
          $signed = true;
          $zerofill = false;
          $length = $m[1];
          return  "int(".$length.") ".($signed ? "" : "UNSIGNED")." ".($zerofill == true ? " ZEROFILL" : "");
        },
      '/^varchar\(([^)]*)\)$/' => function ($m) { 
          $signed = true;
          $zerofill = false;
          $length = $m[1];
          return  "varchar(".$length.")";
        },
      '/^datetime$/' => function () { return 'datetime'; },
      '/^time$/' => function () { return 'time'; },
      '/^timestamp$/' => function () { return 'timestamp'; },
      '/^text$/' => function () { return 'text'; },

    ), $field['type']);
    $field_extra = "";
    if (isset($field['db_specific']['on_update_current_timestamp'])
       && $field['db_specific']['on_update_current_timestamp']
    ){
        $field_extra = "ON UPDATE CURRENT_TIMESTAMP";
    };
    return 
        $field['name']
      . " "
      . $t
      .(d($field, 'nullable', false) ? " NULL " : " NOT NULL ")
      .$field_extra
      .(!isset($field['comment']) ? "" : " COMMENT \"".$field['comment'].escapeChars("\"")."\"")
      .(!isset($field['default']) ? "" : " default ".$field['default'])." "
      .((!isset($field['references']) || !$include_references)
        ? ""
        : " REFERENCES ".$field['references']['table']."(".$field['references']['field'].")"
       );
  }

  public function migrate_to_sql($to){
    $r = array();

    $changes = both_left_right($this->tableNames(), $to->tableNames());

    $new_fields = array();

    // to be dropped tables:
    foreach ($changes['left'] as $tn){
      $txx = $this->tableByName($tn);
      $r[] = "DROP TABLE ". $txx['name'];
    }
    // create new tables, without REFERENCES first, use ALTER TABLE later to
    // break circular dependencies
    foreach ($changes['right'] as $tn){
      $table = $to->tableByName($tn);
      $sql = "CREATE TABLE ". $table['name']. "(\n";
      $sql .= implode(",\n", array_map(function($field){ return DBMySQLScheme::sql_field($field, false); } , $table['fields']))."\n";
      $new_fields[] = array('tn' => $tn, 'fields' =>  $table['fields']);
      $sql .= ") engine = innodb default character set = utf8 collate = utf8_general_ci";
      $r[] = $sql;
    }

    // handle table modifications
    foreach ($changes['both'] as $tn){
      $new_ = $to->tableByName($tn);
      $old_ = $this->tableByName($tn);
      $name = function ($x) { return $x['name']; };
      $f_changes = both_left_right(array_map($name, $old_['fields']), array_map($name, $new_['fields']));
      $new_fs = array_filter($new_['fields'], function($nf)use(&$f_changes){ return in_array($nf['name'], $f_changes['right']); });

      // haxe can't compare array contents, so turn into strings
      $to_s = function($a){ return array_map('serialize', $a); };

      $index_changes  = both_left_right($to_s($old_['indexes']), $to_s($new_['indexes']));
      $uindex_changes = both_left_right($to_s($old_['uniqIndexes']), $to_s($new_['uniqIndexes']));

      $old_pk_s = implode(',', $old_['primaryKeyFields']);
      $new_pk_s = implode(',', $new_['primaryKeyFields']);

      // drop indexes
      if ($old_pk_s != $new_pk_s && $old_pk_s != "")
        $r[] = ("ALTER TABLE ".$tn." DROP PRIMARY KEY");
      foreach ($index_changes['left'] as $drop)
        $r[] = ("ALTER TABLE ".$tn." DROP INDEX ".$tn."_". str_replace(',','_', $drop));
      foreach ($uindex_changes['left'] as $drop)
        $r[] = ("ALTER TABLE ".$tn." DROP INDEX ".$tn."_". str_replace(',','_', $drop));

      // drop fields
      foreach ($f_changes['left'] as $drop)
        $r[] = ("ALTER TABLE ".$tn." DROP ".$drop);
      // don't care about order for now

      // add fields
      foreach ($new_fs as $new_){
        $r[] = ("ALTER TABLE ".$tn." ADD ".DBMySQLScheme::sql_field($new_, false));
      }
      $new_fields[] = array('tn' => $tn, 'fields' => $new_fs );

      // change fields
      foreach ($f_changes['both'] as $c)
        if (serialize($to->tableField($tn, $c)) != serialize($this->tableField($tn, $c))){
          $r[] = ("ALTER TABLE ".$tn." CHANGE ".$c. " ".DBMySQLScheme::sql_field($to->tableField($tn, $c), true));
        }

      // create new indexes
      if ($old_pk_s != $new_pk_s && $new_pk_s != "")
        r.push("ALTER TABLE ".$tn." ADD PRIMARY KEY(".$new_pk_s.")");

      foreach ($index_changes['left'] as $i)
        $r[] = ("ALTER TABLE ".tn." CREATE INDEX ".$tn."_".str_replace(",","_", $i)."(".$i.")");
      // create new unique indexes
      foreach ($uindex_changes['left'] as $i)
        $r[] = ("ALTER TABLE ".tn." CREATE UNIQUE INDEX ".$tn."_".str_replace(",","_", $i)."(".$i.")");

    }

    // change new fields adding references
    foreach ($new_fields as $x)
      foreach($x['fields'] as $f)
        if (isset($f['references']))
          $r[] = ("ALTER TABLE ".$x['tn']." CHANGE ".$f['name']. " ".DBMySQLScheme::sql_field($f, true));

    return $r;
  }

}

class DBScheme{
  public $scheme;

  function __construct($scheme){
    $this->scheme = $scheme;

    foreach ($this->scheme['tables'] as &$table) {
      if (!isset($table['primaryKeyFields'])) {
        $table['primaryKeyFields'] = array();
      }
      if (empty($table['indexes'])) $table['indexes'] = array();
      if (empty($table['uniqIndexes'])) $table['uniqIndexes'] = array();
    }; unset($table);
  }

  public function tableNames() {
    return array_map(function($x){ return  $x['name']; }, $this->scheme['tables']);
  }

  public function tableByName($name) {
    foreach ($this->scheme['tables'] as &$table) {
      if ($table['name'] == $name)
        return $table;
    }; unset($table);
    return null;
  }

  public function tableField($table, $field) {
    $t = $this->tableByName($table);
    if (is_null($t)) return null;
    
    foreach ($t['fields'] as &$f) {
      return $f;
    }; unset($f);
    return null;
  }

  public function check() {
    $errors = array();
    $s =& $this->scheme;

    foreach ($s['tables'] as $table) {
      // check for duplicate fields
      $dups = array_unique(array_map(function ($field) { return $field['name']; }, $table['fields']));
      if (count($dups) != count($table['fields']))
        $errors[] = ("duplicate fields in table ".$table['name']);

      // check references section
      foreach ($table['fields'] as &$f) {
        # dropping placeholder in { .. }, you should have a mapping such as inoremap <s-cr> <esc>o to get there
        if (isset($f['references'])) {
            if (null !== $this->tableField($f['references']['table'], $f['references']['field']))
              $errors[] = $table['name'].".".$f['name']." references unkown field". $f['references']['table'] . "." . $f['references']['field'];
          }
        }

      // check primary key
      foreach ($table['primaryKeyFields'] as $key) {
        if (!in_array($table['fields'], $key))
          $errors[] = ($table['name']." references unkown primary key field ". $key);
      }

      // check uniq keys
      foreach (array_merge($table['uniqIndexes']) as $key) {
        if (!in_array($table['fields'], $key))
          $errors[] = ($table['name']." references unkown uniq index field ". $key);
      }
    }

    return $errors;
  }

  // returns list of SQL commands which must be run to upgrade
  // this scheme to to scheme
  // return array(sql-string)
  public function migrate_to_sql($to){
    // TODO
    return array();
  }

  // dump SQL creating this scheme
  public function sql(){
    return $this->emptyScheme()->migrate_to_sql($this);
  }

  public function emptyScheme() {
    return  new DBScheme(array('tables' => array()));
  }

  static public function migrationFile($dir, $nr, $ext){
    return $dir."/Migration".$nr.".".$ext;
  }

  static public function versionByMigrationDir($dir) {
    $nr = 0;
    while (file_exists(DBScheme::migrationFile($dir, $nr+1, "dump"))) $nr++;
    return $nr;
  }
  
  // compare current scheme against latest known scheme at the end of all
  // migrations. If they differ create a new migration
  public function updateMigrationFiles($dir) {
    $nr = DBScheme::versionByMigrationDir($dir);
    $f = function($nr, $ext)use($dir){ return DBScheme::migrationFile($dir, $nr, $ext); };
    $empty_ = clone($this);

    $new_serialized = serialize($this);
    $old_serialized = ($nr == 0)
      ? ""
      : file_get_contents($f($nr,"dump"));

    if ($new_serialized == $old_serialized) return;

    $empty_->scheme['tables'] = array();
    $last =
     ($nr == 0)
     ? $empty_
     : unserialize($old_serialized);
    $migration_sql = $last->migrate_to_sql($this);
    // save dump
    echo("writing "+$f($nr+1,"dump"));
    file_put_contents($f($nr+1,"dump"), $new_serialized);
    // save sql
    echo ("writing "+$f($nr+1,"sql"));
    file_put_contents($f($nr+1,"sql"), implode(";\n\n", array_merge(array("-- generated fdile"), $this->sql())));
    // save migration
    $migration = array();
    $migration[] = "<?php";
    $migration[] = "throw new Exception('review, then remove this line');";
    $migration[] = "function migrate_to_".($nr+1).'($fun_exec_sql){';
    foreach ($migration_sql as $x){
      $migration[] =  '  $fun_exec_sql(' . var_export($x, true) .");";
    }
    $migration[] = "}";

    $hxFile = $f($nr+1,"php");
    file_put_contents($hxFile, "// generated file \n".implode("\n\n", $migration));
  }

}

class Migration {
  function __construct($migration_dir, $scheme_definition){
    $this->migration_dir = $migration_dir;
    $this->scheme_definition = $scheme_definition;
  }

  function updateMigrationFiles(){
    $scheme = new DBMySQLScheme($this->scheme_definition);
    $scheme->updateMigrationFiles($this->migration_dir);
  }

  function migrate($fun_exec_sql){
    global $db;
    $this->updateMigrationFiles();

    try {
      $version = 1+ $db->queryGenauEineZeileEinWert('SELECT max(version) FROM version');
    } catch (Exception $e) {
      $version = 1;
    }
    while (file_exists($migration_file = $this->migration_dir.'/Migration'.$version.'.php')){
      require_once $migration_file;
      $fun = 'migrate_to_'.$version;
      call_user_func($fun, $fun_exec_sql);
      $fun_exec_sql('INSERT INTO version (version) VALUES ('.$version.')');
      $version ++;
    }
  }
}
