<?php

/* Example usage: (without ajax)

  class MyForm extends Form {
     public function setup_fields(){
        $this->fields['name'] = array('type' => 'text', 'value' => '', 'title' => "Login");
        $this->fields['password'] = array('type' => 'password', 'value' => '', 'title' => "Passwort");
        $this->fields['tos_accepted'] = array('type' => 'checkbox_Y_N', 'value' => 'N', 'title' => ['html' => "Passwort"], 'checks' => ['check_must_be_Y'], 'error_hint' => 'TOS MUST BE ACCEPTED');
     }

     // optionall override this for custom styling, see default implementation
     // public function inner_html(){
     // }
  }

  $form = new MyForm(array('name' => 'mein_login_form'));

  if ($form->from_post($_POST)){
    // alle Felder ok,

    print $form->fields['name']['value'];
    redirect( .. );

  } else {
    print $form->html();
  }

*/

/* Example usage: (with ajax)

  class MyForm extends Form {
     public function setup_fields(){
        $this->fields['name'] = array('type' => 'text', 'value' => '', 'title' => "Login");
        $this->fields['password'] = array('type' => 'password', 'value' => '', 'title' => "Passwort");
     }

     // throw 
     public function ajax_js(){
       return 'js do whatever you want';
     }
  }

  $form = new MyForm(array('name' => 'mein_login_form'));
  echo $form->html();


*/

class Form {
  use Opts;
  use PageComponent;

  public function __construct($opts = []){
    $this->opts = $opts;
    $this->force_id();
    A::ensure($this->opts, 'tag_attributes', []);
    A::ensure($this->opts['tag_attributes'], 'id', $this->opts['id']);
    A::ensure($this->opts, 'name', $this->opts['id'].'_form_name');
    A::ensure($this->opts, 'fields', []);
    A::ensure($this->opts, 'errors', []);
    A::ensure($this->opts, 'form_attributes', []);
    A::ensure($this->opts['form_attributes'], 'name', $this->opts['name']);
    A::ensure($this->opts['form_attributes'], 'id', $this->opts['form_attributes']['name']);
    A::ensure($this->opts['form_attributes'], 'method', 'post');
    A::ensure($this->opts['form_attributes'], 'enctype', 'multipart/form-data');
    A::ensure($this->opts, 'ajax', !A::get_or($this->form_attributes, 'action', false)); // if no action is set assume ajax form
    A::ensure($this->opts, 'field_html_fun', array('Form', 'sample_field_html_fun'));
    A::ensure($this->opts, 'errornous_field_fun', array('Form', 'sample_errornous_field_fun'));

    if ($this->ajax){
      // if using this option all opts values must be serializable !
      $this->opts['iframe_name'] = $this->id.'_iframe';
      $this->opts['form_attributes']['target'] = $this->opts['iframe_name'];
      $this->opts['form_attributes']['action'] = $this->thisUrl([]);
    }

    $this->setup_fields();
  }

  public function setup_fields(){
  }

  static public function sample_field_html_fun($name, $opts){
    // Beispielimplementation wie HTML Felder erstellt werden können
    $type   = A::get_or($opts, 'type');
    $errors = A::get_or($opts, 'errors');
    $value = A::get_or($opts, 'value');

    $tag_attributes = A::get_or($opts, 'tag_attributes', Tag::tag_attributes([]));
    if (!is_null($errors)){
      $tag_attributes['classes'][] = 'errors';
    }
    $tag_attributes['name'] = $name;
    $tag_attributes['id'] = $opts['id'];
    $tag_attributes['title'] = A::get_or($opts, 'title');
    $tag_attributes['placeholder'] = A::get_or($opts, 'placeholder');

    switch ($type) {
      case 'ck_textarea':
        $html = Tag::textarea($tag_attributes, $value)
	  .'<script>CKEDITOR.replace('.json_encode($tag_attributes['id']).')</script>';
      case 'text':
      case 'password':
        $tag_attributes['type'] = $type;
        $tag_attributes['value'] = $value;
        $html = Tag::input($tag_attributes);
        break;
      case 'select':
        $html = Tag::select($tag_attributes,
          implode(array_map(function($option){
            return Tag::render_option($option);
          }, $opts['options']))
        );
        break;
      case 'checkbox_Y_N':
        $tag_attributes['type'] = 'checkbox';
        $tag_attributes['value'] = $value;
        if ($value == 'Y')
          $tag_attributes['checked'] = 'checked';
        $html = Tag::input($tag_attributes);
        break;
      default:
        throw new Exception($type);
    }
    if (!isset($html))
      throw new Exception('TODO '.$type);

    $f = $opts['errornous_field_fun'];
    if (!is_null($f)) {
      $html = $f($errors, $html);
    }
    return $html;
  }

  static public function sample_errornous_field_fun($errors, $html){
    if (count($errors) > 0){
      $html .= Tag::div('error_hint', implode('<br/>', array_map(['Tag','enc'], $errors)));
    }
    return $html;
  }


  public function value_from_array(array $a, $opts = []){
    $fields =& $this->opts['fields'];
    $dv = A::get_or($opts, 'drop_values', true);
    foreach ($fields as $name => $field){
      if ($dv && array_key_exists('value', $fields[$name]))
        unset($fields[$name]['value']);
      if (array_key_exists($name, $a)){
        $fields[$name]['value'] = $a[$name];
      }
    }
  }

  public function from_post($post, $defaults = []){
    if (array_key_exists('form_name_'.$this->opts['name'], $post)){
      $this->value_from_array($post, ['drop_values' => true]);
      $this->check();
      return !$this->has_errors(); // Form erneut anzeigen, wenn Fehler
    } else {
      $this->value_from_array($defaults);
      return false; // Form anzeigen
    }
  }

  public function values(){
    $r = [];
    foreach ($this->opts['fields'] as $key => $field) {
      $r[$key] = $field['value'];
    }
    return $r;
  }

  public function preprocess_form_values(){
    foreach ($this->opts['fields'] as &$f) {
      switch ($f['type']) {
        case 'checkbox_Y_N':
          $f['value'] = isset($f['value']) ? 'Y' : 'N';
          break;
        default:
          break;
      }
    }
  }

  public function check_values(){
    foreach ($this->opts['fields'] as &$f) {
      foreach(A::get_or($f, 'checks', []) as $check){
        $set_field_error = function($err)use(&$f){
          A::push_unique($f, 'errors', A::get_or($f, 'error_hint', $err));
        };
        $value = A::get_or($f, 'value', null);
        if (is_null($check)) continue;
        if (is_string($check)){
          switch ($check) {
            case 'check_given':
              if ($value == '' || is_null($value))
                $set_field_error(__('must be given'));
              break;
            case 'check_email_or_empty':
              if (!preg_match('/^[^0-9][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[@][a-zA-Z0-9_]+([.][a-zA-Z0-9_]+)*[.][a-zA-Z]{2,4}$/', $value))
                $set_field_error(__('must be an email'));
              break;
            case 'check_must_be_Y':
              if ($f['value'] != 'Y')
                $set_field_error(__('must be checked'));
                A::push($f, 'checks_run', $check);
              break;
            default:
              if (!in_array($check, A::get_or($f, 'checks_run', [])))
                throw new Exception('bad check '. $check);
              break;
          }
          A::ensure($f, 'checks_run', []);
          $f['checks_run'][] = $check;
        }
      }
    }
  }

  public function check(){
    $this->preprocess_form_values();
    $this->check_values();
  }


  public function has_errors(){
    foreach($this->opts['fields'] as $field){
      if (count(A::get_or($field, 'errors', [])) > 0)
        return true;
    }
    if (count($this->opts['errors']) > 0)
      return true;
    return false;
  }

  public function field_opts($name, $opts){
    $field_opts = array_replace($this->opts['fields'][$name], $opts);
    $field_opts['id'] = $this->opts['name'].'_'.$name;
    return $field_opts;
  }

  public function field($name, $opts = []){
    $field_opts = $this->field_opts($name, $opts);
    A::ensure($field_opts, 'errornous_field_fun', A::get_or($field_opts, 'errornous_field_fun', $this->opts['errornous_field_fun']));

    // finde field_html_fun in $this->opts['fields']['field_html_fun'] oder $this->opts['field_html_fun'] und rufe sie auf:
    $name = $name;
    $f = A::get_or($field_opts, 'field_html_fun', $this->opts['field_html_fun']);
    return call_user_func($f, $name, $field_opts);
  }
  public function inner_html(){
    // sample implementation, Template aufrufen oder so ..
    $html = $this->errors_box();
    foreach($this->opts['fields'] as $name => $field){
      $html .= $this->field($name, $field).'<br/>';
    }
    $html .= $this->submit('submit');
    return $html;
  }

  public function form_html(){
    return
      Tag::form($this->form_attributes,
        $this->inner_html()
        .Tag::input(array('type' => 'hidden', 'name' => 'form_name_'.$this->opts['name'], 'value' => ""))
      );
  }

  public function html(){
    // the form gets wrapped in a div so that both the form and the iframe can
    // be removed/replaced in one go easily
    return $this->tag_html(
      $this->form_html()
      .(($this->ajax)
        ? Tag::iframe(['name' => $this->iframe_name, 'style' => 'width:1px;height:1px;opacity:0.1'])
        : ''
      )
    );
  }

  // utils
  public function errors_box(){
    if (count($this->opts['errors']) > 0)
      return Tag::div('errors_box', implode(', ', $this->opts['errors']));
    return '';
  }

  public function submit($text){
    # return Tag::input(array('type' => 'submit', 'value' => $text, 'name' => $this->opts['name']));
    return Tag::button(array('class' => 'css3button', 'type' => 'submit', 'value' => $this->opts['name']), $text);
  }

  public function field_with_description($name, $opts = []){
    $field_opts = $this->field_opts($name, $opts);
    list($key, $title) = A::first_key($field_opts, ['description', 'title']);

    return
      Tag::div('field_with_description', 
        Tag::label(array('for' => $field_opts['id']),
          Tag::div(array('class' => "field_label"), Tag::enc_allow_html($title))
        )
        .$this->field($name, $opts)
      );
  }

  public function field_container($html){
    return Tag::div(array('class' => 'field_container'), $html);
  }

  public function js($js){
    // escape from iframe, eval js in parent document
    return Tag::htmlDoc(H::js('parent.window.jQuery.globalEval('.json_encode($js).');'));
  }

  public function ajax_js(){
    return 'alert("override ajax_js")';
  }

  public function page_component_ajax_reply(){
    if ($this->from_post($_POST)){
      // form ok, return form action
      return $this->js($this->ajax_js());
    } else {
      // form not ok, return form again with error hints
      $html = $this->form_html();
      return $this->js('$("#"+'.json_encode($this->form_attributes['id']).').outer_html('.json_encode($html).');');
    }
  }
}
