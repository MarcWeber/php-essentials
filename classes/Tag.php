<?php

/* Verwendung:
  Tag::div('class', 'inner_html');
  Tag::form('array('class' => 'foo', 'id' => 'bar'), 'inner_html');
*/

class Tag {

  static public function tag_attributes(){
    return array('classes' => array());
  }

  static public function enc_allow_html($s){
    if (is_array($s) && array_key_exists('html', $s))
      return $s['html'];
    return htmlentities($s, ENT_QUOTES, "UTF-8" );
  }

  static public function enc($s){
    if (is_array($s) && array_key_exists('html', $s))
      $s = $s['html'];
    return htmlentities($s, ENT_QUOTES, "UTF-8" );
  }

  static public function render_tag_start(&$tag){
    if (in_array($tag['tag'], ['meta','img','link','br','hr','input','area','param','col','base']))
      $tag['autoclose'] = true;
    $s = '<';
    $s .= $tag['tag'];
    if (isset($tag['classes'])) {
      $tag['attributes']['class'] = A::get_or($tag['attributes'], 'class', '').implode(' ', $tag['classes']);
    }
    foreach (A::get_or($tag, 'attributes', []) as $key => $value) {
      if ($key == 'classes') continue;
      $s .= ' '.$key.'="'.self::enc($value).'"';
    }
    $s .= A::get_or($tag, 'verbatim_attrs', '');
    if (A::get_or($tag, 'autoclose', false))
      $s .= '/';
    return $s.'>';
  }

  static public function render_tag_end($tag){
    if (A::get_or($tag, 'autoclose', false))
      return;
    return '</'.$tag['tag'].'>';
  }

  static public function render_tag($tag){
    return self::render_tag_start($tag)
      .A::get_or($tag, 'html', '')
      .self::render_tag_end($tag);
  }

  static public function __callStatic($name, $args){
    $tag = array(
      'tag' => $name,
      'attributes' => is_string($args[0]) ? array('class' => $args[0]) : $args[0],
      'html' => A::get_or($args, 1),
    );
    return self::render_tag($tag);
  }

  // helpers
  static public function htmlDoc($content){
          return '<html><head><title></title></head><body>'.$content.'</body></html>';
  }

  static public function render_option($option){
    $o = ['value' => $option['value']];
    if (A::get_or($option, 'selected', false))
      $o['selected'] = 'selected';
    return Tag::option($o,
      array_key_exists('html', $option)
      ? $option['html']
      : Tag::enc($option['text'])
    );
  }
}


