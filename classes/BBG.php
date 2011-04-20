<?php

/* license: LGPL
 * auhtor: Marc Weber
 * copyright: MArc Weber 2011
 *
 * given an existing picture size and a size describing string calc calculates 
 * new height/width values keeping height/width ratio.
 * The string is
 * a-X : X * X defines target area of the image. Thus landscape and portrait 
 *       images appear to have a very similar size
 * w-X : X is target width
 * h-X : X is target height
 *
 *
 * BGG_URL returns the url of an image
 * BGG_PATH returns the storage location of an image
 *
 */

define('PIC_ORIGINAL_SIZE','original_blid');

if (!function_exists('BGG_URL')){
    // url for img tags
    function BGG_URL($group, $id, $groesse, $path_parameter = null){
        return MEDIEN_URL."/$group/$id-$groesse.jpg";
    }
}

if (!function_exists('BGG_PATH')){
    // location of storage. All sizes should be put into the same path
    // so that they can be deleted using -*- glob patterns again.
    function BGG_PATH($group, $id, $groesse, $path_parameter = null){
        return MEDIEN_PATH."/$group/$id-$groesse.jpg";
    }
}

class BBG{

    static public function calc_img($path, $target_size_str){
    $size = getimagesize($path); // [0] = breite [1] = hoehe , ...
            return self::calc($size[0], $size[1], $target_size_str);
    }

    // target size
    //$target_size_typ
    //  b-200 : Breite (in Pixeln)
    //  h-400 : Höhe (in Pixeln)
    //  a-100 : Fläche (in Pixeln ^2), entspricht Fläche von BreitexHöhe = 10x10
    //  --- : Keine Änderung
    static public function calc($breite, $hoehe, $target_size_str){
        $target_size = substr($target_size_str,2);
        $target_size_typ = substr($target_size_str,0,1);
        switch($target_size_typ) {
            case 'w':
                // Breite Vorgegeben 
                $new_breite = $target_size;
                
                $new_hoehe = round($new_breite * $hoehe / $breite);
                break;
            case 'h':
                // Höhe vorgeben
                $new_hoehe = $target_size;
                $new_breite = round($new_hoehe * $breite / $hoehe);
            break;
            case 'a':
                // Fläche Vorgegeben 
                $new_hoehe = sqrt($target_size * $target_size / $breite * $hoehe);
                $new_breite = round($new_hoehe * $breite / $hoehe);
            break;
            case 'f':
              list($w,$h) = explode('X', $target_size);
              list($new_breite,$new_hoehe) = self::calc($breite, $hoehe, 'h-'.$h);
              if ($new_breite > $w || $new_hoehe > $h){
                list($new_breite,$new_hoehe) = self::calc($breite, $hoehe, 'w-'.$w);
              }
            break;
            case '-':
                $new_breite = $breite; $new_hoehe = $hoehe;
            break;
            default:
                throw new Exception("unbekanntes Bildgroesse!:".$target_size_typ);
        }
        return array((int) $new_breite, (int) $new_hoehe);
    }

    // result is always a jpeg
    static public function resizeImage($image_path, $target_path, $target_size, 
        $also_enlarge = false, $symlink = false, $jpg_quality = 85){
        $size = getimagesize($image_path); // [0] = breite [1] = hoehe , ...
        list($b,$h) = self::calc($size[0], $size[1], $target_size);

        $d = dirname($target_path);
        if (!is_dir($d))
          mkdir($d,'0755',true);

        if ( (!($b > $size[0] || $h > $size[1]))  // nicht grösser 
            || $also_enlarge
            || !in_array(pathinfo($image_path,PATHINFO_EXTENSION), array("jpg","jpeg")) // Bild kein jpeg 
            ){
            // recreate
            $img_orig = imagecreatefromstring(file_get_contents($image_path));
            $img_dest = imagecreatetruecolor($b, $h);
            imagecopyresampled($img_dest, $img_orig, 0, 0, 0, 0, $b, $h, $size[0], $size[1]);
            // save
            imagejpeg($img_dest, $target_path, $jpg_quality);
        } else {
            // eventually create symlink only to reduce storage size
            if ($symlink)
                symlink($target_path, $image_path); // both should be absolute paths
            else
                copy($image_path, $target_path);
        }

        if (!file_exists($target_path))
            throw new Exception( 'target image '.$target_path." could'nt be created");

        return $size;
    }

    // System spezifische Funktionen
    // Jedes Bild gehört zu einer GRUPPE und hat eine ID
    // Aus GRUPPE und ID und Grösse ergibt sich der Datei und URL-PFAD.
    // BUILD_GRUPPEN_URL
    //
    // $groesse = "---" : wird nicht umgerechnet 
    // $groesse = "h-100" "w-100" "f-10000"
    static public function pic_from_post($file, $group, $id, $groesse = "---"){
        $ziel = BGG_PATH($group, $id, PIC_ORIGINAL_SIZE);
        $size = self::resizeImage($file['tmp_name'], $ziel, $groesse, false);
        return array($size[0],$size[1]);
    }

    // $id im Format "name,breite,höhe
    static public function pic($group, $id_value, $groesse, $beschreibung = '', $path_parameter = null){
    	if (is_null($id_value))
    		return "no pic assigned";
    	try {
	        list($id, $orig_b, $orig_h) = explode(',', $id_value);
	
	        $url = BGG_URL($group, $id, $groesse, $path_parameter);
	        $path_orig = BGG_PATH($group, $id, PIC_ORIGINAL_SIZE, $path_parameter);
	        $path_richtige_groesse = BGG_PATH($group, $id, $groesse, $path_parameter);
	
	        list($b,$h) = self::calc($orig_b, $orig_h, $groesse);
	
	        if (!file_exists($path_richtige_groesse)){
	            self::resizeImage($path_orig, $path_richtige_groesse, $groesse, false);
	        }
	        return '<img width="'.$b.'" height="'.$h.'" src="'.$url.'" alt="'.htmlentities($beschreibung).'"/>';
    	} catch (Exception $e) {
    		global $log;
    		$msg = "Fehler bei Bilderzeugung $group $id_value $groesse $beschreibung";
    		$log->fehler($msg.' '.Trace::exceptionToText($e));
    		return $msg;
    	}
    }
    
    // deletes images based on group and id using glob pattern
    static public function delete($cache_only, $group, $id = '*') {
        $glob_cache = glob($a=BGG_PATH($group,  $id, "*"));
        $glob_original = glob($b=BGG_PATH($group, $id , PIC_ORIGINAL_SIZE));
        $combine = $cache_only ? "array_diff" : "array_merge";
        foreach (array_unique($combine($glob_cache,$glob_original)) as $file){
        	unlink($file);
        }
    }
    

}
