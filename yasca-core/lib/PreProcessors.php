<?php
/**
 * Pre-Processing Functions
 * @license see doc/LICENSE
 */

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
                $line = str_replace(array("\n", "\r", "\t"), " ", $line);
                $line = trim($line);
            }
        $output .= $line;
        } else {
        $output .= $line . "\n";
    }
    }
    return explode("\n", $output);
}

function cfml_fix_tag_attributes($file_contents) {
    $result = array();
    for ($i=0; $i<count($file_contents); $i++) {
        $line = trim($file_contents[$i]);
        $j=0;
        while (preg_match("/\s*\<[^\>]*\s*$/", $line) && $j++ < 20) {
            $line .= " " . trim($file_contents[$i+$j]);
        }
        $i += $j;
        $line = str_replace(">", ">\n", $line);
        $line = trim($line);
        array_push($result, $line);
    }

    $result2 = array();
    foreach ($result as $tag) {
        if (!preg_match("/\<([^\s]+)\s(.*)\>/", $tag, $tag_attr_list)) {
            continue;
        }

        $cf_tag = $tag_attr_list[1];
        $cf_attr = $tag_attr_list[2];
        $attribute_list = preg_split("/\s+/", $cf_attr);

        sort($attribute_list);

        $attr_str = trim(implode(" ", $attribute_list));
        $attr_str = rtrim($attr_str, ">");
        array_push($result2, "<" . $cf_tag . " " . $attr_str . ">");
    }
    return $result2;
}

//This function is used to remove line continuation
//characters and their accompanying crlf to cause
//asp code lines to not be split across multiple lines
function asp_remove_line_continuation($file_contents) {
    $file_contents = implode("\n", $file_contents);
    $file_contents = preg_replace("/_\s*/", "", $file_contents);
    return explode("\n", $file_contents);
}
?>
