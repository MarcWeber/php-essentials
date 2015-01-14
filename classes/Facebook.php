<?php

class Facebook {

  static public function js_setup_conversion_pixel(){
    return H::js(" 
        // Facebook Conversion Code for holifestival-marc 
        (function() {
          var _fbq = window._fbq || (window._fbq = []);
          if (!_fbq.loaded) {
            var fbds = document.createElement('script');
            fbds.async = true;
            fbds.src = '//connect.facebook.net/en_US/fbds.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(fbds, s);
            _fbq.loaded = true;
          }
        })();
        window._fbq = window._fbq || [];
    ");
  }

  static public function conversion_pixel_html($opts){
    return (defined('FACEBOOK_TRACKING_PIXEL') 
      ? '<noscript><img height="1" width="1" alt="" style="display:none" src="https://www.facebook.com/tr?ev='.FACEBOOK_TRACKING_PIXEL.'&amp;cd[value]='.$opts['value'].'&amp;cd[currency]=EUR&amp;noscript=1" /></noscript>' 
      : '');
  }

  static public function conversion_pixel_js($opts){
    return (defined('FACEBOOK_TRACKING_PIXEL') 
      ? "\nwindow._fbq.push(['track', '".FACEBOOK_TRACKING_PIXEL."', {'value':'".$opts['value']."','currency':'EUR'}]); " 
      : '');
  }
}
