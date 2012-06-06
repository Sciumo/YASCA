<?php

/**
 * This file contains miscellaneous analytical functions that assist in the analysis of
 * source code.
 * @license see doc/LICENSE
 * @package Yasca
 */ 

/**
 * Finds non-static member variables of a class.
 * WARNING: Sending $file_contents as a string will cause the file to be allocated in memory again.
 * @param mixed $file_contents string of file contents or array of lines.
 * @param array $line_matches Output only. The lines that contain the found variables. 
 * @return array of variable names.
 */
function find_member_variables($file_contents, array &$line_matches = null) {
    $b=0;
    $variables = array();
    
    if (!is_array($file_contents))
        $file_contents = explode("\r\n", $file_contents);
       
    $count = count($file_contents);
        
    for ($i=0; $i<$count; $i++) {
        $line = $file_contents[$i];
        if (strstr($line, "{")) {
            ++$b;
        }
        if (strstr($line, "}")) {
            --$b;
        }
        if ($b == 1) {
            $matches = array();

            if ( (preg_match('/^[^=]*\s+([a-zA-Z0-9_]+)\s*;/', $line, $matches) ||
                  preg_match('/([a-zA-Z0-9_]+)\s*=/', $line, $matches)) &&
                 !preg_match('/static/', $line) ) {
                $variables[$i] = $matches[1];
                
                if (isset($line_matches))
                    $line_matches[$i] = $file_contents[$i];
            }

        }
    }    
    return $variables;
}

/**
 * Gets method contents from a specific file.
 * WARNING: Sending $file_contents as a string will cause the file to be allocated in memory again.
 * @param array $file_contents 
 * @param string $method_name
 */
function get_method_contents($file_contents, $method_name) {
    $b=0;
    $methods = array();
    $in_method = false;
    
    if (!is_array($file_contents))
        $file_contents = explode("\r\n", $file_contents);
    
    $count = count($file_contents);
    for ($i=0; $i<$count; $i++) {
        $line = $file_contents[$i];
        
        $b += substr_count($line, "{");
        $b -= substr_count($line, "}");
        
        if ($b == 1 || ($b == 2 && substr_count($line, "{") == 1)) {
            $matches = array();
            if ( (preg_match('/^[^=]*?([a-zA-Z0-9_]+)\s*\(/', $line, $matches) &&
                  $matches[1] == $method_name) ) {
                $in_method = true;
            } else if ( $i > 0 &&
                        preg_match('/^[^=]*?([a-zA-Z0-9_]+)\s*\(/', $file_contents[$i-1], $matches) &&
                        $matches[1] == $method_name) {
                $in_method = true;
            }
        }
        
        if ($in_method) { 
            $methods[$i] = $line;
        }
        if ($b <= 1) $in_method = false;
    }
    return $methods;
}
?>
