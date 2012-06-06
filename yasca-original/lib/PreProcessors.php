<?php
/* Pre-Processing Functions */

/**
 * This is a pre-processing function for ColdFusion code that uses the cfx_ingres tag
 * to remove extra lines from the sql attribute.
 */
function trim_cfx_ingres_sql($file_contents) {
    $output = "";
    $line = "";
    $in_sql = false;
    foreach ($file_contents as $line) {
        if (preg_match("/sql\s*=\s*\"\s*$/i", $line)) {
            $in_sql = true;
        }
        if ($in_sql) {
            if (preg_match("/\"\>/", $line)) {
                $in_sql = false;
                $line = trim($line);
            }
            if ($in_sql) {
                $line = str_replace("\n", " ", $line);
                $line = str_replace("\r", " ", $line);
                $line = str_replace("\t", " ", $line);
                $line = trim($line);
            }
        $output .= $line;
        } else {
        $output .= $line . "\n";
    }
    }
    return explode("\n", $output);
}
?>