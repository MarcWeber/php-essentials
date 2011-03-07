<?php

class XMLValidation {

  // dosen't validate using dtd (how to set this up without refetching the dtd?
  // it ensures that opening and closing tags match etc.
  static function validateXML($xml){
  
          libxml_use_internal_errors(true);
  
          // TODO use doctype?
          $doc = new DOMDocument('1.0', 'utf-8');
          $doc->loadXML($xml);
  
          $errors = libxml_get_errors();
          if (empty($errors))
          {
                  return $xml;
          }
  
  
          $error_lines = '';
  
          foreach ($errors as $error) {
  
                  if ($error->level <= 2) // not defined entitiy ..
                          continue;
  

				  if ($error->message == "EntityRef: expecting ';'\n")
					  continue;
                  # if $error->message
  
                  # if ($error->level < 3)
                  # {
                  #       return $xml;
                  # }
  
                  $lines = explode("\n",$xml);
                  $xmls = $error->message.' '.$error->level."\n";
                  $el = $error->line;
                  for ($i = -7; $i < 5; $i++) {
                          $l = $el + $i - 3;
                          if ($i >= 0 && $i < count($lines))
                                  $xmls .= ($l+1).': '.g($lines,$l,'')."\n";
                  }
  
                  $error_lines .= "\n".$xmls."\n";
          }
  
          if ($error_lines != '')
                  // should only print relevant lines..
                  throw new Exception("error validating XML line ".$el." xml:\n".$error_lines);
  }

  // use htmltidy class to validate html
  static public function validateHTML($html){
    if (!is_callable('tidy_parse_string'))
      throw new Exception('tidy not supported');

    $config = array('indent' => TRUE,
                    'output-xhtml' => TRUE,
                    'wrap' => 200);

    $tidy = tidy_parse_string($html, $config, 'UTF8');
    $tidy->cleanRepair();

    // note the difference between the two outputs
    if ($tidy->errorBuffer != '')
      throw new Exception($tidy->errorBuffer);
  }

}
