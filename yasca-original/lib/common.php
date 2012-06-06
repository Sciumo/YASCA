<?php
/**
 * This file contains miscellaneous functions, as well as some PHP 5 functions that
 * will be included when running on a PHP 4 environment.
 * @package Yasca
 */

include_once("Color.php");
 
/**
 * Does the provided string start with a specific substring? Case sensitive.
 * @param string $str string to search
 * @param string $sub substring to look for in $str
 * @return true iff $str starts with $sub.
 */
function startsWith( $str, $sub ) {
   return ( substr( $str, 0, strlen( $sub ) ) === $sub );
}

function endsWith( $str, $sub ) {
   return ( substr( $str, strlen( $str ) - strlen( $sub ) ) === $sub );
}

function flatten_array($value, $key, &$array) {
    if (!is_array($value))
        array_push($array,$value);
    else
        array_walk($value, 'flatten_array', $array);
 
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

/* PHP 4 Functions */

if(PHP_VERSION < "5.0.0") {
    function strripos($haystack, $needle, $offset=0) {
        if($offset<0){
            $temp_cut = strrev(  substr( $haystack, 0, abs($offset) )  );
        }
        else{
            $temp_cut = strrev(  substr( $haystack, $offset )  );
        }
        $pos = strlen($haystack) - (strpos($temp_cut, strrev($needle)) + $offset + strlen($needle));
        if ($pos == strlen($haystack)) { $pos = 0; }
       
        if(strpos($temp_cut, strrev($needle))===false){
             return false;
        }
        else return $pos;
    }
}

if(!function_exists("stripos")){
    function stripos($haystack, $needle, $offset=0) {
        return strpos(strtolower($haystack), strtolower($needle), $offset);
    }
}

if( !function_exists('memory_get_usage') ) {
    function memory_get_usage() {
        //If its Windows
        //Tested on Win XP Pro SP2. Should work on Win 2003 Server too
        //Doesn't work for 2000
        //If you need it to work for 2000 look at http://us2.php.net/manual/en/function.memory-get-usage.php#54642
        if ( substr(PHP_OS,0,3) == 'WIN')
        {
               if ( substr( PHP_OS, 0, 3 ) == 'WIN' )
                {
                    $output = array();
                    exec( 'tasklist /FI "PID eq ' . getmypid() . '" /FO LIST', $output );
       
                    return preg_replace( '/[\D]/', '', $output[5] ) * 1024;
                }
        }else
        {
            //We now assume the OS is UNIX
            //Tested on Mac OS X 10.4.6 and Linux Red Hat Enterprise 4
            //This should work on most UNIX systems
            $pid = getmypid();
            $output = array();
            exec("ps -eo%mem,rss,pid | grep $pid", $output);
            $output = explode("  ", $output[0]);
            //rss is given in 1024 byte units
            return $output[1] * 1024;
        }
    }
}

if ( !function_exists('file_put_contents') ) {
    define('FILE_APPEND', 1);
    function file_put_contents($n, $d, $flag = false) {
        $mode = ($flag == FILE_APPEND || strtoupper($flag) == 'FILE_APPEND') ? 'a' : 'w';
        $f = @fopen($n, $mode);
        if ($f === false) {
            return 0;
        } else {
            if (is_array($d)) $d = implode($d);
            $bytes_written = fwrite($f, $d);
            fclose($f);
            return $bytes_written;
        }
    }
}

if ( !function_exists('sys_get_temp_dir') )
{
    // Based on http://www.phpit.net/
    // article/creating-zip-tar-archives-dynamically-php/2/
    function sys_get_temp_dir()
    {
        // Try to get from environment variable
        if ( !empty($_ENV['TMP']) ) {
            return realpath( $_ENV['TMP'] );
        } elseif ( !empty($_ENV['TMPDIR']) ) {
            return realpath( $_ENV['TMPDIR'] );
        } elseif ( !empty($_ENV['TEMP']) ) {
            return realpath( $_ENV['TEMP'] );
        } else {
            // Try to use system's temporary directory
            // as random name shouldn't exist
            $temp_file = tempnam( md5(uniqid(rand(), TRUE)), '' );
            if ( $temp_file ) {
                $temp_dir = realpath( dirname($temp_file) );
                unlink( $temp_file );
                return $temp_dir;
            } else {
                return FALSE;
            }
        }
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
 * Checks to see if the given filename has a passed extension. $filename does not have
 * to be an actual existing file.
 * @param string $filename filename to check
 * @param mixed $ext string extension or array of extensions to check. Should not include a period.
 * @return true iff filename matches one of the extensions, or if $ext was an empty array.
 */
function check_in_filetype($filename, $ext = array()) {
    $ext_valid = false;
    if (is_array($ext)) {
        if (count($ext) == 0) return true;      // $ext=() means all accepted
        for ($i=0; $i<count($ext); $i++) { 
            if (endsWith($filename, "." . $ext[$i])) {
                $ext_valid = true;
            }
        }
    } else {
        $ext_valid = endsWith($filename, ".$ext");
    }
    return $ext_valid;
}

/**
 * This function takes all files under $start_dir and places them in
 * $dest_dir. All files are transformed from:
 * ./foo/bar/quux to
 * ./foo_bar_quux
 * If there are any naming conflicts, the conflicts' basename will
 * have a random (non-conflicting) 4-character string appended to it.
 */
function collapse_dir($dest_dir, $file_type_list=array(), $start_dir=".", &$translation) {
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

if (!function_exists('fnmatch')) {
        function fnmatch($pattern, $string) {
            return @preg_match(
                '/^' . strtr(addcslashes($pattern, '/\\.+^$(){}=!<>|'),
                array('*' => '.*', '?' => '.?')) . '$/i', $string
            );
        }
}

/**
 * Generates a random alphanumeric string.
 */
function random_string($length=10) {
    $pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
    $key  = $pattern{rand(0,36)};
    for($i=1;$i<$length;$i++) {
        $key .= $pattern{rand(0,36)};
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
    print "$url\n";
    if (!preg_match('/(https?\:\/\/[^\/]+\/)/i', $url, $matches)) {
        return "";
    }
    $baseurl = $matches[1];

    $html = file_get_contents($url . "&printable=yes");
    $html = str_replace(array("\n","\r","\r\n"), "", $html);

    if (!preg_match('/<!-- start content -->(.*)<!-- end content -->/i', $html, $matches)) {
        return false;
    }
    $html = trim($matches[1]);
    
    //$html = preg_replace('/href\s*=\s*"\//', 'href="$baseurl/', $html);
    print $html;
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
    for ($i=0; $i<strlen($a); $i++) {
        if (substr($b, $i, 1) == substr($a, $i, 1)) {
            $k++;
        } else {
            return $k;
        }
    }
}

/**
 * Extracts class name from a file.
 * @param string $filename filename to scan 
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

function getSystemOS() {
    @ob_start();
    @phpinfo(1);
    $info = @ob_get_contents();
    @ob_end_clean();
    
    if (preg_match('/System \=\> ([^\s]+)/mi', $info, $matches)) {
    return $matches[1];
    }
    return "Unknown";
}

function isWindows() {
    return getSystemOS() == 'Windows';
}

function isLinux() {
    return getSystemOS() == 'Linux';
}

function is_valid_regex($regex) {
    $orig_err = error_reporting(0);
    preg_match($regex, "");
    error_reporting($orig_err);
    if (preg_last_error() == PREG_NO_ERROR) {
    return true;
    } else {
    return false;
    }
}
function highlight($string, $color="%W") {
    return Console_Color::convert("%$color$string%n");
}

function any_within($haystack, $needle, $max_distance = 10) {
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

function correct_slashes($path) {
    if (isWindows()) {
    $path = str_replace("/", "\\", $path);
    } else {
    $path = str_replace("\\", "/", $path);
    }
    return $path;
}

?>
