<?php

class GoogleAnalytics {

  static public function push($opts){
    if (array_key_exists('value', $opts)) {
      // must be int
      if  (!preg_match('/^[0-9]+$/', $opts['value']))
        throw new Exception('bad value '.$opts['valu']);
      $opts['value'] = (int) $opts['value'];
    }
    $args = [];
    $args[] = '_trackEvent';
    $args[] = $opts['category'];
    $args[] = A::get_or($opts, 'action', 'action');
    $args[] = A::get_or($opts, 'label', 'label');
    if (array_key_exists('value', $opts))
      $args[] = $opts['value'];
    return call_user_func_array(['JsonWithJS', 'call_method'], ['_gaq.push', $args]);
  }

  static public function js($opts){
    return " 
     var _gaq = _gaq || [];
      _gaq.push(['_setAccount', ".json_encode($opts['UA_CODE'])."]);
      _gaq.push(['_trackPageview']);

      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    ";
  }
}
