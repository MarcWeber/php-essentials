<?php

class GoogleAnalyticsKeywords {

  static public function data_to_csv_rows($data){
     $headlines = [ 'Keyword-Status', 'Keyword', 'Übereinstimmungstyp', 'Kampagne', 'Anzeigengruppe', 'Status', 'Max. CPC für Keyword', 'Max. CPC für Anzeigengruppe', 'Ziel-URL', 'Klicks', 'Impressionen', 'CTR', 'Durchschn. CPC', 'Kosten', 'Durchschn. Position', 'Label', 'Keyword-ID', 'Anzeigengruppen-ID' ];

     $must_have_keys = ['Keyword', 'Kampagne', 'Anzeigengruppe'];

     $r = [];

     $r[] = $headlines;
     foreach ($data as $v) {
       $bad_keys =     array_diff(array_keys($v), $headlines);
       $missing_keys = array_diff($must_have_keys, array_keys($v));

       if (count($bad_keys) > 0)
         throw new Exception("bad keys: ".var_export($bad_keys, true));

       if (count($missing_keys) > 0)
         throw new Exception("missing keys: ".var_export($bad_keys, true));

       $rv = [];
       foreach ($headlines as $k) {
         $rv[] = (array_key_exists($k, $v)) ? $v[$k] : null;
       }
       $r[] = $rv;
     }
     return $r;
  }

  static public function to_csv_file($file, $data){
    $csv_writer = new CSVWriter($file, ',');
    $csv_writer->writeAll(self::data_to_csv_rows($data));
    $csv_writer->close();
  }

}
