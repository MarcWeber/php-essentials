<?php

/*
Verwendung:
  $template = new Template([
    'template_dir' => dirname(__FILE__).'/templates'
    'implementations' => [
      ['Template', 'simple_php_file_handler'] 
    ]
  ]);
  echo $template->seite(['content' => 'inner html']);
*/

class Template {

  public function __construct($opts){
    $this->opts = $opts;
  }

  public function by_name($template){
    $args = func_get_args();
    $method = $args[0];

    echo "<!-- template START :$method -->\n";
    $done = false;
    foreach ($this->opts['implementations'] as $impl) {
      if ($impl($this, $method, $args)){
        $done = true;
        break;
      }
      if (!$done)
        throw new Exception('no handler');
    }

    echo "<!-- template END :$method -->\n";
    return ob_get_clean();
  }

  public function __call($method, $args){
    return call_user_func_array(array($this, 'by_name'), array_merge(array($method), $args));
  }

  public function for_each($it, $a, $ar){
    $html = '';
    $nr = 0;
    foreach($it as $item){
      $nr+=1;
      $opts = [];
      $opts['nr'] = $nr;
      $opts['id'] = $nr-1;
      if (is_array($it))
      $opts['last'] = $nr == count($it);
      $html .= $this->by_name($a, $opts, $ar);
      $ar));
    }
    return $html;
  }

  // use .php as template files
  static public function php_handler($template, $method, $args){
    $file = $template->opts['template_dir'].'/'.$method.'.php';
    if (!file_exists($file)) return false;
    $args = array_slice($args, 1);
    foreach($args as $a)
      extract($a);
    ob_start();
    require $file;
    return true;
  }

  // haml-to-php.com
  static public function haml_to_php_handler($template, $method, $args){
    $file = $template->opts['template_dir'].'/'.$method.'.haml';
    if (!file_exists($file)) return false;
    echo haml($method, $args[0]);
  }
}
