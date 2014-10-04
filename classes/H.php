<?php 

# constructing HTML tags:
# H::a(array('href' => '#'), htmlentities('foo'));
class H {

  static public function render_tag_start(&$tag){
    if ($tag['tag'] == 'input')
      $tag['autoclose'] = true;
    $s = '<';
    $s .= $tag['tag'];
    $s .= ' ';
    foreach (d($tag, 'attributes', array()) as $key => $value) {
      $s .= ' '.$key.'="'._htmlentities($value).'"';
    }
    if (isset($tag['classes'])) {
      $s .=' class="'._htmlentities(implode(' ', $tag['classes'])).'"';
    }
    $s .= d($tag, 'verbatim_attrs', '');
    if (d($tag, 'autoclose', false))
      $s .= '/';
    return $s.'>';
  }

  static public function render_tag_end($tag){
    if (d($tag, 'autoclose', false))
      return;
    return '</'.$tag['tag'].'>';
  }

  static public function render_tag($tag){
    return self::render_tag_start($tag)
      .d($tag, 'html', '')
      .self::render_tag_end($tag);
  }

  static public function __callStatic($name, $args){
    $tag = array(
      'tag' => $name,
      'attrs' => $args[0],
      'html' => $args[1],
    );
    return self::render_tag($tag);
  }

  static public function menu_item($menu_item){
    return self::li(array(), 
      self::a(array('href' => '#'),
        self::menu_title($menu_item)
      )
    );
  }
}
