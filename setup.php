<?php

/* load this in each php file .. */
define('LIB',dirname(__FILE__));

require_once 'config.php';

{ // strip slashes
    if (get_magic_quotes_gpc()){
            function strip_slashes_deep($value) {
                            return is_array($value) ? array_map('strip_slashes_deep', $value) : stripslashes($value);
            };
            $_GET = array_map('strip_slashes_deep', $_GET);
            $_POST = array_map('strip_slashes_deep', $_POST);
            $_COOKIE = array_map('strip_slashes_deep', $_COOKIE);
    }
}

{ # XXX
  /* einfache Fehlerbehandlung (definiert exception_handler Funktion)
     dann einfach all top level php Dateien so einschliessen:
  try {
	require_once 'lib/setup.php';

	DEIN CODE
	
  } catch (Exception $e){
	uncaught_exception($e);
  }

  */

  require_once 'error-handling.php';

  function handle_unexpected_failure($message, $trace){

	$time = date(DATE_ATOM);

	$message = "$time $message";

	try { @ob_end_flush(); } catch (Exception $e) { }

	try {
	  $trace = Trace::traceToText($trace);
	} catch (Exception $e){
	  $trace = array('exception in handler!');
	}

	echo "
	  Unerwarteter Fehler. Dieser Vorfall wurde geloggt: $time.<br/>
	  Wenn der Fehler in nach 24h immer noch besteht, bitte kontaktieren: ".ERROR_CONTACT_EMAIL."<br/>
	  <br/>
      <br/>
	  Unexpected failure. this incident was logged: $time<br/>
	  If this failure still happens in 24h please contact us: ".ERROR_CONTACT_EMAIL."<br/>

	  <br/>\n
	  <br/>\n
	  $message<br/>\n
	  ".str_replace("\n","<br/>\n", $trace)
	  ."\n";



	$all = "$message\n$trace";

	try {
		file_put_contents(ERROR_LOG_FILE, $all."\n", FILE_APPEND | LOCK_EX);
		chmod(ERROR_LOG_FILE, 0777);
        } catch (Exception $e){ $all .= "\n".var_export($e, true); }


	$header = 'ERROR '.(defined('PROJECT_ID') ? constant('PROJECT_ID') : 'NO PROJECT ID').' '.$time;
	$emails = explode(',', ERROR_CONTACT_EMAIL);

	foreach ( $emails as $email ){
		$args = array($email, $header, "$message\n$trace");
	  if (! call_user_func_array('mail', $args) )
		  echo "Email verschicken an Admin fehlgeschlagen\n";

	  echo var_export($args, true);
	}
	echo "<br/>notification mail with header ".$header." was sent to ".count($emails)." admins addresses\n";
	exit();

  }

}

{ // automatically find classes by filename:

	function autload_class($class){
		if (in_array($class , (array('parent')))) return false;
		$f = LIB_PATH.'/classes/'.$class.'.php';
		if (file_exists($f)){
			require_once $f;
			return true;
		}
		return false;
	}

	spl_autoload_register('autload_class');

}

require_once 'lib/lib.php';


{ // HAML setup
  require_once 'lib/Haml.php' ;
  $haml = new HamlFileCache(LIB.'/haml', LIB.'/haml-cache');
  # $haml->forceUpdate = true;
  // $haml->options['ugly'] = false;
  @ini_set('xdebug.max_nesting_level','600');
}

@ini_set('xdebug.max_nesting_level','600');

$db->execute("SET sql_mode = 'STRICT_ALL_TABLES'");
$db->execute("SET NAMES 'utf8'");
