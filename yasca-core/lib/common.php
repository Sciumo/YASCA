<?php

/**
 * This file contains miscellaneous functions
 * @license see doc/LICENSE
 * @package Yasca
 */
 
/**
 * Does the provided string start with a specific substring? Case sensitive.
 * @param string $str string to search
 * @param string $sub substring to look for in $str
 * @return boolean true iff $str starts with $sub.
 * @todo rename to starts_with for consistency
 */
function startsWith( $str, $sub ) {
   return ( substr( $str, 0, strlen( $sub ) ) === $sub );
}

/**
 * Does the provided string end with a specific substring? Case sensitive.
 * @param string $str string to search
 * @param string $sub substring to look for in $str
 * @return boolean true iff $str ends with $sub.
 * @todo rename to ends_with for consistency
 */
function endsWith( $str, $sub ) {
   return ( substr( $str, 0 - strlen( $sub ) ) === $sub );
}

/**
 * @return boolean true iff success
 */
function flatten_array(array $value, $key, &$array) {
	return array_walk_recursive($value, 
		function ($v, $k) use (&$array){$array[] = $value;});
}

function ellipsize($string, $limit, $repl = "...", $strip_tags = true) {
    if(strlen($string) > $limit) {
        if ($strip_tags) {
            return substr_replace(strip_tags($string),$repl,$limit-strlen($repl));
        } else {
            return substr_replace($string, $repl, $limit-strlen($repl));
        }
    } else {
        return $string;
    }
}

function unlink_recursive($dir, $del_self = false) {
    if(!$dh = @opendir($dir)) return;
    while (false !== ($obj = readdir($dh))) {
        if($obj=='.' || $obj=='..') continue;
        if (!@unlink($dir.'/'.$obj)) unlink_recursive($dir.'/'.$obj, true);
    }
    if ($del_self){
        closedir($dh);
        @rmdir($dir);
    }
}

/**
 * Encodes a string in a format similar to base64, but that can be used as a filename.
 */
function base64_encode_safe($text) {
    $t = base64_encode( gzcompress($text, 9) );
    $t = str_replace("+", "_", $t);
    $t = str_replace("/", "-", $t);
    return $t;
}

/**
 * Decodes a string in a format similar to base64.
 */
function base64_decode_safe($text) {
    $t = $text;
    $t = str_replace("-", "/", $t);
    $t = str_replace("_", "+", $t);
    $t = base64_decode( gzuncompress($t) );
    return $t;
}

/** 
 * Checks to see if $needle is anywhere within any of the components of $haystack.
 * Works recursively.
 * @return boolean
 */
function substr_in_array($needle, $haystack) {
    if (!is_string($needle) ||
        !is_array($haystack)) {
        return false;
    }
    
    for ($i=0; $i<count($haystack); $i++) {
        if (is_array($haystack[$i])) {
            return substr_in_array($needle, $haystack[$i]);
        } elseif (stripos($haystack[$i], $needle) !== FALSE) {
            return true;
        }
    }
    return false;
}

/**
 * This function takes all files under $start_dir and places them in
 * $dest_dir. All files are transformed from:
 * ./foo/bar/quux to
 * ./foo_bar_quux
 * If there are any naming conflicts, the conflicts' basename will
 * have a random (non-conflicting) 4-character string appended to it.
 */
function collapse_dir($dest_dir, array $file_type_list=array(), $start_dir=".", &$translation) {
    if (!is_dir($dest_dir) || !is_dir($start_dir)) return;
    $file_list = Yasca::dir_recursive($start_dir);
    if ($file_list === false) return;
    $file_id = 0;
    foreach ($file_list as $filename) {
        $pinfo = pathinfo($filename);
        $ext = (isset($pinfo['extension']) ? $pinfo['extension'] : "");
        
        $rel_filename = str_replace(array($start_dir), "", $filename);
        
        $rel_filename = ++$file_id . ".$ext";
        $translation[$file_id] = $filename;
        
        $dest_dir = str_replace("\\", "/", $dest_dir);
        
        if (count($file_type_list) == 0 || in_array(strtolower($ext), $file_type_list)) {
            copy ($filename, $dest_dir . $rel_filename);
        }
    }
}

/**
 * Generates a random alphanumeric string.
 * Warning: Do not use for cryptographic purposes.
 */
function random_string($length=10) {
    $pattern = "1234567890abcdefghijklmnopqrstuvwxyz"; 
    $key  = $pattern[rand(0,36)];
    for($i=1;$i<$length;$i++) {
        $key .= $pattern[rand(0,36)];
    }
    return $key;
}

function mime_extract_rfc2822_address($string) {
    //rfc2822 token setup
    $crlf           = "(?:\r\n)";
    $wsp            = "[\t ]";
    $text           = "[\\x01-\\x09\\x0B\\x0C\\x0E-\\x7F]";
    $quoted_pair    = "(?:\\\\$text)";
    $fws            = "(?:(?:$wsp*$crlf)?$wsp+)";
    $ctext          = "[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F" .
                      "!-'*-[\\]-\\x7F]";
    $comment        = "(\\((?:$fws?(?:$ctext|$quoted_pair|(?1)))*" .
                      "$fws?\\))";
    $cfws           = "(?:(?:$fws?$comment)*(?:(?:$fws?$comment)|$fws))";
    //$cfws           = $fws; //an alternative to comments
    $atext          = "[!#-'*+\\-\\/0-9=?A-Z\\^-~]";
    $atom           = "(?:$cfws?$atext+$cfws?)";
    $dot_atom_text  = "(?:$atext+(?:\\.$atext+)*)";
    $dot_atom       = "(?:$cfws?$dot_atom_text$cfws?)";
    $qtext          = "[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F!#-[\\]-\\x7F]";
    $qcontent       = "(?:$qtext|$quoted_pair)";
    $quoted_string  = "(?:$cfws?\"(?:$fws?$qcontent)*$fws?\"$cfws?)";
    $dtext          = "[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F!-Z\\^-\\x7F]";
    $dcontent       = "(?:$dtext|$quoted_pair)";
    $domain_literal = "(?:$cfws?\\[(?:$fws?$dcontent)*$fws?]$cfws?)";
    $domain         = "(?:$dot_atom|$domain_literal)";
    $local_part     = "(?:$dot_atom|$quoted_string)";
    $addr_spec      = "($local_part@$domain)";
    $display_name   = "(?:(?:$atom|$quoted_string)+)";
    $angle_addr     = "(?:$cfws?<$addr_spec>$cfws?)";
    $name_addr      = "(?:$display_name?$angle_addr)";
    $mailbox        = "(?:$name_addr|$addr_spec)";
    $mailbox_list   = "(?:(?:(?:(?<=:)|,)$mailbox)+)";
    $group          = "(?:$display_name:(?:$mailbox_list|$cfws)?;$cfws?)";
    $address        = "(?:$mailbox|$group)";
    $address_list   = "(?:(?:^|,)$address)+";

    //apply expression
    preg_match_all("/^$address_list$/", $string, $array, PREG_SET_ORDER);

    return $array;
}

/**
 * Loads an external vulnerability from OWASP
 */
function get_owasp_vulnerability_content($url) {
    $matches = array();
    if (!preg_match('/(https?\:\/\/[^\/]+\/)/i', $url, $matches)) {
        return "";
    }
    $baseurl = $matches[1];
	//@todo Disable this function, as using an arbitrary url passed in is a security flaw.
    $html = file_get_contents($url . "&printable=yes");
    $html = str_replace(array("\n","\r","\r\n"), "", $html);

    if (!preg_match('/<!-- start content -->(.*)<!-- end content -->/i', $html, $matches)) {
        return false;
    }
    $html = trim($matches[1]);
    
    //$html = preg_replace('/href\s*=\s*"\//', 'href="$baseurl/', $html);
    if (function_exists("tidy_repair_string")) {
        return tidy_repair_string($html);
    } else {
        return $html;
    }
}

function find_similar_text($haystack, $needle, $minimum_similarity = 0) {
    $closest_sim = -1;
    $closest_hay = false;

    foreach ($haystack as $hay) {
        $sim = find_matching_prefix_length($hay, $needle);
        if ($sim > $closest_sim) {
            $closest_sim = $sim;
            $closest_hay = $hay;
        }
    }
    
    if ($closest_sim >= $minimum_similarity)
        return $closest_hay;
    else
        return false;
}

function find_matching_prefix_length($a, $b) {
    $k=0;
    $len_a = strlen($a);
    for ($i=0; $i<$len_a; $i++) {
        if (substr($b, $i, 1) == substr($a, $i, 1)) {
            $k++;
        } else {
            return $k;
        }
    }
}

/**
 * Extracts the first class name from a file.
 * @param string $filename filename to scan 
 * @return string Name of the first class in the file or False.
 */
function get_class_from_file($filename) {
    $fc = file_get_contents($filename);
    if ($fc === false) {
        return false;
    }
    $matches = array();
    preg_match('/^\s*class\s*([^\s\{]+)/im', $fc, $matches);
    return $matches[1];
}

//@todo Rename get_system_os
function getSystemOS() {
	//This is called many times and it's slow, so cache the result.
	static $result = null;
	if (!isset($result)){
	    @ob_start();
	    @phpinfo(1);
	    $info = @ob_get_contents();
	    @ob_end_clean();
	    
	    if (preg_match('/System \=\> ([^\s]+)/mi', $info, $matches)) {
	    	$result = $matches[1];
	    }else {
	    	$result = "Unknown";
	    }
	}
	return $result;
}

//@todo rename is_windows
function isWindows() {
	return getSystemOS() == 'Windows';
}

//@todo rename is_linux
function isLinux() {
    return getSystemOS() == 'Linux';
}

//@todo rename wine_exists
function wineExists(){
	//This is called many times and it's slow, so cache the result.
	static $result;
	if (!isset($result))
		$result = !preg_match("/no wine in/", `which wine`);
	return $result;
}

function is_valid_regex($regex) {
    $orig_err = error_reporting(0);
    preg_match($regex, "");
    error_reporting($orig_err);
    return (preg_last_error() == PREG_NO_ERROR);
}

function any_within(array $haystack, $needle, $max_distance = 10) {
    if (!is_numeric($needle)) return false;

    foreach ($haystack as $hay) {
        if (!is_numeric($hay)) continue;
        if ($hay < $needle &&
            $needle - $hay <= $max_distance) {
            return true;
    	}
    }
    return false;
}

/**
 * 
 * @param array $array The array to search
 * @param closure $closure A closure accepting one parameter for an item in the array and that returns true/false. 
 * @return boolean true iff there is at least one item in the array where $closure($item) returns true.
 */
function array_any(array $array, $closure){
	foreach($array as $item){
		if ($closure($item)) return true;
	}
	return false;
}

/**
 * 
 * @param array $array The array to search
 * @param closure $closure A closure accepting one parameter for an item in the array and that returns true/false. 
 * @return mixed The first &$item in the array that satisfies $closure($item), or null if none exist.
 */
function &array_first(array $array, $closure){
	foreach($array as &$item){
		if ($closure($item)) return $item;
	}
	return $item = null;
}

/**
 * 
 * @param array $array The array to select unique values on
 * @param closure $closure A closure accepting one parameter for an item in the array and that returns the values to consider items unique by.
 * @param int $sort_flags The sort_flags constants used by array_unique. Optional.
 * @return array The resulting unique array. Keys are preserved.
 */
function array_unique_with_selector(array $array, $closure, $sort_flags = SORT_STRING){
	$selected_values;
	foreach($array as $key => $item){
		$selected_values[$key] = $closure($item);
	}
	$selected_values = array_unique($selected_values, $sort_flags);
	return array_intersect_key($array,$selected_values);
}

/**
 * This function corrects slashes based on the platform.
 * @param string $path The absolute or relative filepath
 * @param boolean $endWithSlash If true, the returned path will end in a slash. If is false and $path ends in a slash, the ending slash is preserved. Defaults to false.
 * @return string The corrected path.
 */
function correct_slashes($path, $endWithSlash = false) {
    return preg_replace("/[\\/]+/", DIRECTORY_SEPARATOR, 
    	trim($endWithSlash ? $path . DIRECTORY_SEPARATOR : $path));
}

/** 
 * Converts UTF-16 to UTF-8 character sets.
 * Thanks to http://www.moddular.org/log/utf16-to-utf8
 * @todo Deprecate when http://www.php.net/manual/en/book.mbstring.php or PHP 6 is available.
 * @var string
 */
function utf16_to_utf8($str) {	
	if (strlen($str) <= 2) return $str;
	
    $c0 = ord($str[0]);
    $c1 = ord($str[1]);

    if ($c0 == 0xFE && $c1 == 0xFF) {
        $be = true;
    } else if ($c0 == 0xFF && $c1 == 0xFE) {
        $be = false;
    } else {
        return $str;
    }

    $len = strlen($str);
    //This function does not know how to interpret an odd number of bytes
    if ($len % 2 != 0) return $str;
    
    $str = substr($str, 2);
    $len -= 2;
    $dec = '';
    for ($i = 0; $i < $len; $i += 2) {
        $c = ($be) ? ord($str[$i]) << 8 | ord($str[$i + 1]) : 
                ord($str[$i + 1]) << 8 | ord($str[$i]);
        if ($c >= 0x0001 && $c <= 0x007F) {
            $dec .= chr($c);
        } else if ($c > 0x07FF) {
            $dec .= chr(0xE0 | (($c >> 12) & 0x0F));
            $dec .= chr(0x80 | (($c >>  6) & 0x3F));
            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
        } else {
            $dec .= chr(0xC0 | (($c >>  6) & 0x1F));
            $dec .= chr(0x80 | (($c >>  0) & 0x3F));
        }
    }
    return $dec;
}

function dir_recursive($start_dir, $file_types = array()) {
    $files = array();
    $start_dir = str_replace("\\", "/", $start_dir);    // canonicalize

    if (is_dir($start_dir)) {
        $fh = opendir($start_dir);

        while (($file = readdir($fh)) !== false) {
            if (strcmp($file, '.')==0 || strcmp($file, '..')==0) continue;

            $filepath = $start_dir . '/' . $file;
            if ( is_dir($filepath) ) {
                $files = array_merge($files, dir_recursive($filepath, $file_types));
            } else {
				if (count($file_types) == 0) {
					array_push($files, $filepath);
				} else {
                	foreach ($file_types as $file_type) {
                    	if (endsWith($file, $file_type)) {
                        	array_push($files, $filepath);
                    	}
                	}
				}

            }
        }
        closedir($fh);
    } else {
        $files = false;
    }
    return $files;
}
?>
