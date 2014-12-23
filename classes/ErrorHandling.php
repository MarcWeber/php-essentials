<?php

/* global error / exception handling
 *
 * The set_error_handler is used to throw an Exception for everything which 
 * could look like a failure. Fatal errors such as uncaught Exceptions or
 * call to undefined functions are then noticed by the SHUTDOWN_FUNCTION 
 * funcction.
 *
 * The hook function custom_failure_handler is called unless undefined.
 * An example (writing the trace to a file readable by Vim) could look like this:
 *
 * Optionally surround everything by a global try catch calling 
 * uncaught_exception(array('exception') => $ex) so that custom_failure_handler has a nice trace 
 * object
 *
	function custom_failure_handler($message, $trace){
	  global $errorfile;

	  $s = '';
	  foreach( $trace as $k){
		$s.="\n".(isset($k['file']) ? $k['file'] : 'nofile') 
		  .':'.(isset($k['line']) ? $k['line'] : 'no line');
	  }

	  file_put_contents($errorfile, str_replace('<br>',"\n",$message) . $s );
	  // assign 777 because I sometimes use PHP in console..
	  chmod($errorfile, 0777);
	}
 *
 * license: do the fuck you want with it
 *
 * Eg in index.php use ..
  try {

    ErrorHandling::setup(array(
        'ERROR_CONTACT_EMAIL' => ERROR_CONTACT_EMAIL,
        'unexpected_failure_handlers' => array(array('ErrorHandling', 'example_handle_unexpected_failure'))
    ));

    your code
	
  } catch (Exception $e){
	uncaught_exception(array('exception' => $e, 'silent' => false));
  }

  */

function unexpected_failure($o){

  // for development (feed traces to editor or ...)
  if (function_exists('custom_failure_handler')){
     custom_failure_handler($o);
  }

  // should be defined in your setup file
  ErrorHandling::handle_unexpected_failure($o);

}

{ # implementation exceptions
  # undefinierte variablen fangen etc.
  # note: The shutdown handler above would be run on exceptions as well
  # however catching it means we have a stack trace

  function uncaught_exception($o){
    $o['message'] = Trace::exceptionToText($o['exception']);
    $o['trace'] = Trace::exFullTrace($o['exception']);
    unexpected_failure($o);
  }
}

class ErrorHandling {

  static $opts = null;


  static public function handle_unexpected_failure($o){
    foreach(self::$opts['unexpected_failure_handlers'] as $f){
      call_user_func($f, $o);
    }
  }

  static public function setup($opts){

    self::$opts = $opts;

    {
      # make everything unexpected be an error and cause a trace by throwing an 
      # exception which is catched by either the exception implementation or by the 
      # shutdown handler finally
      function ERROR_HANDLER($error_type, $error_msg, $error_file, $error_line, $error_context){
        if ($error_msg == 'Call-time pass-by-reference has been deprecated')
          return ;
       throw new Exception($error_type.' '.$error_msg);
      }
      set_error_handler('ERROR_HANDLER');
    }

    { # implementation fatal errors.

      function SHUTDOWN_FUNCTION() {
        // if you don't wrap this you'll get "Exception with no stack frame .. good 
        // luck then!"
        try {
          if (defined('DEBUG_MYSQL_QUERIES')){
            global $mysql_queries, $db_total;
            # echo "total mysql time ".(isset($db_total) ? $db_total : '')."\n";
            $count = 0;
            if (is_array($mysql_queries))
              foreach ($mysql_queries as $a) {
                if ($count ++ > 500){
                  echo "skipping more sql queries\n";
                  break;
                }
                echo "\n====================\n".$a['sql']."\n";
                echo "called in\n";
                foreach ($a['trace'] as $t) {
                  echo g($t,'file','').':'.g($t,'line','')."\n";
                }
              }
          }

          $error = error_get_last(); 
          if (!$error) return; // no error
            /* 
               $error looks like this:
               array (
                    'type' => 1,
                    'message' => 'Call to undefined function foo()',
                    'file' => 'test.php',
                    'line' => 19
                    )
             */
          try {
            if (!$error) return; // no error

          if (strpos(var_export($error,true),'opendir(/tmp') !== FALSE)
            return ; // ignorieren

            unexpected_failure(array('message' => $error['type'].' '.$error['message'], 'trace' => array($error)));

          } catch (Exception $e) {
            # if formatting fails for whatever reason:
            unexpected_failure(array(
              'trace' => array(),
              'message' => '? '.var_export($e,true).var_export($error, true)
            ));
          }

        } catch (Exception $e) {
           var_dump($e); // show user
           uncaught_exception(array('exception' => $e));
           exit(1);
        }
      } 
      register_shutdown_function('SHUTDOWN_FUNCTION'); 

    }

  }

  // utils
  # function sendeException($e, $m=''){
  #   $message = "$m\n".Trace::exceptionToText($e);
  #   $time = date(DATE_ATOM);
  #   $emails = explode(',', ERROR_CONTACT_EMAIL);
  #   $header = 'ERROR '.(defined('PROJECT_ID') ? constant('PROJECT_ID') : 'NO PROJECT ID').' '.$time;
  #   foreach ( $emails as $email ){
  #     $args = array($email, $header, "$message");
  #     call_user_func_array('mail', $args);
  #   }
  # }

  static public function example_handle_unexpected_failure($o){
    $message = $o['message'];
    $trace = $o['trace'];
    $silent = H::get_or($o, 'silent', false);

    $time = date(DATE_ATOM);

    $message = "$time $message";

    try { @ob_end_flush(); } catch (Exception $e) { }

    try {
      $trace = Trace::traceToText($trace);
    } catch (Exception $e){
      $trace = array('exception in handler!');
    }

    if (!$silent){
      echo " 
        Unerwarteter Fehler. Dieser Vorfall wurde geloggt: $time.<br/>
        Wenn der Fehler in nach 24h immer noch besteht, bitte kontaktieren: ".self::$opts['ERROR_CONTACT_EMAIL']."<br/>
        <br/>
          <br/>
        Unexpected failure. this incident was logged: $time<br/>
        If this failure still happens in 24h please contact us: ".self::$opts['ERROR_CONTACT_EMAIL']."<br/>

        <br/>\n
        <br/>\n
        $message<br/>\n
        ".str_replace("\n", "<br/>\n", $trace)
        ."\n";
    }

    $all = "$message\n$trace";

    try {
      file_put_contents(ERROR_LOG_FILE, $all."\n", FILE_APPEND | LOCK_EX);
      chmod(ERROR_LOG_FILE, 0777);
          } catch (Exception $e){
            try {
              try {
                $all .= $e->getMessage();
              } catch (Exception $e) {
                $all .= "\n".$e->getMessage();
              }
            } catch (Exception $e2) {
              $all .= "\n".$e->getMessage()."\n".$e2->getMessage();
            }
          }


    $header = 'ERROR '.(defined('PROJECT_ID') ? constant('PROJECT_ID') : 'NO PROJECT ID').' '.$time;
    $emails = explode(',', self::$opts['ERROR_CONTACT_EMAIL']);

    foreach ( $emails as $email ){
      $args = array($email, $header, "$message\n$trace");
      if (! call_user_func_array('mail', $args) && !$silent )
        echo "Email verschicken an Admin fehlgeschlagen\n";

      if (!$silent)
        echo var_export($args, true);
    }
    if (!$silent)
      echo "<br/>notification mail with header ".$header." was sent to ".count($emails)." admins addresses\n";
  }

}
