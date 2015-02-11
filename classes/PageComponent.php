<?php
/*


 */

trait PageComponent {

  static public function serve($params){ // params is params GET parameter from below
    list($class, $params) = H::unserialize($params);

    $parent = new ReflectionClass($class);
    $ok = false;
    do {
      if (in_array("PageComponent", array_keys($parent->getTraits()))){
        $ok = true;
        break;
      }
    }while ($parent = $parent->getParentClass());
    if (!$ok) {
      throw new Exception('bad class '.$class);
    }

    $class = new $class($params);

    A::ensure($this->opts, 'tag_attributes', []);
    A::ensure($this->opts['tag_attributes'], 'id', $this->id);

    return $class->page_component_ajax_reply();
  }

  static public function url($class, $params){
    return url(['path' => '/pc'.H::url_params([
      'class' => $class,
      'params' => H::serialize([$class, $params])
    ])]);
  }

  public function thisUrl($state){
    return PageComponent::url(H::className($this), array_replace(
      $this->opts, $state
    ));
  }

  public function tag_html($inner_html, $tag = 'div'){
    return call_user_func(['Tag', $tag], $this->opts['tag_attributes'], $inner_html);
  }

  public function js($js){
    return $js;
  }

  public function js_replace_html($opts){
    return '$("#"+'.json_encode(A::get_or($opts, 'id', $this->id)).').outer_html('.json_encode($opts['html']).');';
  }

  static public function uniq_id(){
    return 'id_'.rand(100,1000).'_'.uniqid();
  }

  public function force_id(){
    if (!array_key_exists('id', $this->opts)) {
      A::ensure($this->opts, 'id', $this->uniq_id());
    }
    A::ensure($this->opts, 'tag_attributes', []);
    A::ensure($this->opts['tag_attributes'], 'id', $this->id);
  }

}
