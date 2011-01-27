<?php

/* minimal printf like SQL construction
 * usage:
 *
 * foreach( $db->queryAllRows('SELECT * FROM foo WHERE id > ? AND ?v', 10, new Verbatim("subquery")) as $row){
 * }
 *
 * $db->insert('tablename', array('field1' => $value1));
 * $db->update('tablename', array('field1' => $value1), array('id' =>10) );
 *
 * author: Marc Weber
 * license: LGPL
 * */

class MySQL {

  public $conn; // public is bad? but its more simple

  public function quoteNachTyp($v, $typ = ''){
    $quotingFunctions = array(
      's' => 'quoteString'
      , 'd' => 'quoteDate'
      , 'dt' => 'quoteDateTime'
      , 't' => 'quoteTime'
      , 'i' => 'quoteInt'
      , 'f' => 'quoteFloat'
      , 'b' => 'quoteBoolean'
      , 'o' => 'quoteOp' 
      , 'n' => 'quoteName'
      , 'v' => 'quoteVerbatim'
    );
    if (substr($typ, 1,2) == 'in') {
      $typ = substr($typ,0,1);
      $in = true;
    }
    $qf = (array_key_exists(''.$typ, $quotingFunctions))
      ? $quotingFunctions[$typ] 
      : 'quoteSmart';
    if (isset($in)){

      return '('.implode( array_map(array($this, $qf), $v),', ').')';
    } else return (is_null($v) ? 'NULL' : call_user_func( array($this, $qf ), $v ) );
  }


  /* first arg: sql
   * following arguments values
   *
   * Example:
   *
   * self.printfLike('SELECT * FROM ?t WHERE id in ?in and age > ?2:age', array(20), array('age' => 40))
   */
  public function printfLike(){
    $args = func_get_args();
    if (count($args) == 1)
      return $args[0];
    $sql = current($args); next($args);

    preg_match_all('/(^|\?(?:dt|[tobnfidsv])?(?:in)?)((?::[^\t\r\n) ,:]+)*)([^?]*)/', $sql, $matches);

    $sql = $matches[0][0];

    for ( $i = 1; $i < sizeof($matches[0]); $i++){
      if ($matches[2][$i] == ''){

        $wert = current($args); next($args);
      } else { 
        $wert =& $args;
        $keys = explode(':', substr($matches[2][$i],1));
        foreach( $keys as $k){
          if ((!is_array($wert)) || (!array_key_exists($k,$wert)))
            throw new Exception( 'SQL Parameter Fehler, Parameter ['.implode($keys,',').'] nicht gefunden in '.print_r($args,true));
          $wert =& $wert[$k];
        }
      }
      $type = substr($matches[1][$i], 1, 3);
      $sql .= ( $wert instanceof Verbatim
        ? $wert->toString()
        : $this->quoteNachTyp( $wert, $type ) )
        . $matches[3][$i];
    }
    return $sql;
  }


  // runs mysql_query. Extra function so that time measurement can be added etc
  // @throws Exception
  public function query($sql){
    if ($debug = defined('DEBUG_MYSQL_QUERIES')){
      global $mysql_queries, $db_total;
      if (count($mysql_queries) >= 500)
        $mysql_queries[501] = 'no more queries will be logged';
      else
        $mysql_queries[] = array('sql' => $sql, 'trace' => debug_backtrace());
      $time		= microtime(true); 
    }

    $db_result  = mysql_query($sql,$this->conn);

    if ($debug){
      $time_diff  = microtime(true) - $time;
      $db_total += $time_diff;
    }

    if ($db_result)
      return $db_result;
    else
      throw new Exception(mysql_error($this->conn)."\n query was: \n".$sql);
  }

  public function queryPrintf(){
    $args = func_get_args();
    $sql = call_user_func_array( array($this, 'printfLike'), $args );
    return $this->query($sql);
  }

  public function queryAllRows() {
    $args = func_get_args();
    $r = call_user_func_array( array($this, 'queryPrintf'), $args );
    $result = array();
    while ($row = mysql_fetch_assoc($r))
            $result[] = $row;
	mysql_free_result($r);
    return $result;
  }

  public function queryOneRow() {
    $args = func_get_args();
    $r = call_user_func_array( array($this, 'queryPrintf'), $args );
    $row = mysql_fetch_assoc($r);
    if (mysql_fetch_assoc($r)){
	  mysql_free_result($r);
      throw new Exception('only one row expected - got two');
	}
	mysql_free_result($r);
    return $row;
  }

  public function queryOneValue() {
    $args = func_get_args();
    $r = call_user_func_array( array($this, 'queryPrintf'), $args );
    $row = mysql_fetch_assoc($r);
    if (mysql_fetch_assoc($r)){
	  mysql_free_result($r);
      throw new Exception('only one row expected - got two');
	}
	mysql_free_result($r);
    return current($row);
  }

  public function queryFirstCol(){
	$args = func_get_args();
    
	$re = array();
	foreach( call_user_func_array( array($this, 'queryAllRows'), $args ) as $r){
	  $re[] = current($r);
	}
	return $re;
  }

  public function insert($tabelle, $values, $prefix= '', $alias = null){
    $namen = array(); $v_types = array();

    foreach( $values as $name => $t){
      $namen[] = $this->quoteName($name); 
      $v_values[] = $this->quoteSmart($t);
    }

    $r = $this->queryPrintf(' INSERT INTO '.$this->quoteName($tabelle).((is_null($alias)) ? '' : ' AS '.$this->quoteName($alias) )
      .' ( '. implode($namen, ', ').') VALUES ( '.implode($v_values,', ').')');
	mysql_free_result($r);
    return mysql_insert_id($this->conn);
  }


  public function insertMulti($tabelle, $list, $prefix= '', $alias = null){
    $namen = array(); $v_types = array();

    foreach( $list[0] as $name => $t){
      $namen[] = $this->quoteName($name); 
    }

	foreach ($list as $row) {
		
	  $v_values = array();
	  foreach( $row as $name => $t){
		$v_values[] = $this->quoteSmart($t);
	  }
	  $rs[] = '('.implode($v_values,', ').')';
	}

	$this->queryPrintf(' INSERT INTO '.$this->quoteName($tabelle).((is_null($alias)) ? '' : ' AS '.$this->quoteName($alias) )
	  .' ( '. implode($namen, ', ').') VALUES '.implode(',', $rs));
    return mysql_insert_id($this->conn);
  }


  public function update($tabelle, $values, $prefix= '', $alias = null){
    $felder = isset($this->tabellenUndFeldTypen[$tabelle])
      ? $this->tabellenUndFeldTypen[$tabelle]
      : ($felder = $this->tabellenUndFeldTypen[$tabelle] = $this->queryTabelleFeldTypen($tabelle));

    $namen = array(); $v_types = array(); $updates = array();
    foreach( $felder as $k => $t){
      if (array_key_exists($prefix.$k,$values)){
        $qn = $this->quoteName($k);
        $expr = $t.':1:'.$prefix.$k;
        $namen[] = $qn; 
        $v_types[] = $expr;
        $updates[] = $qn.'='.$expr;
      }
    }
    return $this->insert(' INSERT INTO '.$this->quoteName($tabelle).((is_null($alias)) ? '' : ' AS '.$this->quoteName($alias) )
      .' ( '. implode($namen, ', ').') VALUES ( '.implode($v_types,', ').')'
      .' ON DUPLICATE KEY UPDATE '.implode($updates,','),
        $werte);
  }

  public function quoteSmart($in){
    if (is_int($in)) {
      return $in;
    } elseif (is_float($in)) {
      return $this->quoteFloat($in);
    } elseif (is_bool($in)) {
      return $this->quoteBoolean($in);
    } elseif (is_string($in)) {
      return $this->quoteString($in);
    } elseif (is_null($in)) {
      return 'NULL';
    } else {
      return $this->quoteString($in);
    }
  }

  public function quoteString($s){ return "'".mysql_escape_string($s)."'"; }

  public function quoteBoolean($b){ return $b ? '1' : '0'; }

  public function quoteInt($i){
    if ($i != $i*1)
      throw new Exception( "integer expected");
    return $i*1; 
  }

  public function quoteFloat($f){
    if (preg_match('/[^-0-9.,e]/',$f) > 0)
      throw new Exception( "float expected");
    return $f;
  }
  
  public function quoteTime($d, $quote = true){
    $quote = ($quote) ? '\'' : '';
    if (is_null($d) || $d === '') return null;
    if (preg_match('/^(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2})$/',$d, $m) !== 1)
      throw new Exception( 'time of format hh:mm:ss expected, got: '.$d);
    return "$quote$d$quote";
  }
  public function quoteDate($d, $quote=true){
    $quote = ($quote) ? '\'' : '';
    if (is_null($d) || $d === '') return null;
    if (is_numeric($d)){

      $d = date('H:i:s', $d);
    }

    if (preg_match('/^(\p{Nd}{1,2})\.(\p{Nd}{1,2})\.(\p{Nd}{1,4})$/',$d, $m) !== 1)
      if (preg_match('/^(\p{Nd}{1,4})-(\p{Nd}{1,2})-(\p{Nd}{1,2})$/',$d, $m) !== 1)
        throw new EUser_Message( 'date expected, got "'.$d.'"');
      else return  $quote.sprintf('%04d-%02d-%02d', $m[1], $m[2],$m[3]).$quote;
    return $quote.sprintf('%04d-%02d-%02d', $m[3], $m[2],$m[1]).$quote;
  }
  public function quoteDateTime($d, $quote=true){
    $quote = ($quote) ? '\'' : '';
    if (is_null($d) || $d === '') return null;
    if (is_numeric($d)){

      $d = date('H:i:s', $d);
    }

    if (preg_match('/^(\p{Nd}{1,2})\.(\p{Nd}{1,2})\.(\p{Nd}{1,4})(\s+(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2}))?$/',$d, $m) !== 1)
      if (preg_match('/^(\p{Nd}{1,4})-(\p{Nd}{1,2})-(\p{Nd}{1,2})(\s+(\p{Nd}{1,2}):(\p{Nd}{1,2}):(\p{Nd}{1,2}))?$/',$d, $m) !== 1)
        throw new EUser_Message( 'date expected, got "'.$d.'"');
      else return  $quote
        .sprintf('%04d-%02d-%02d', $m[1], $m[2],$m[3])
        .( isset($m[7]) ? ' '.$m[5].':'.$m[6].':'.$m[7]  : '' )
        .$quote;
    return $quote.sprintf('%04d-%02d-%02d', $m[3], $m[2],$m[1]).$quote;
  }

  public function quoteName($n){ 
    return '`'.str_replace('.','`.`', $n).'`';
  }

  public function quoteVerbatim($s){ return $s; }

}

/* insert text verbatim by using new Verbatim("text");
 */
class Verbatim 
{
  protected $s;
  public function __construct($s)
  {
    $this->s = $s;
  }

  public function toString(){
    return $this->s;
  }
}
