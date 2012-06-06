<?php
/**
 * Result Class
 *
 * This struct holds result information for a particular issue found. There will be
 * one Result object created for each such issue. 
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 1.3
 * @package Yasca
 */
class Result {
    public $filename;                   // name of the file
    public $severity = 5;               // informational
    public $category = "General";       // default value
    public $category_link;              // URL pointing to information about the category
    public $plugin_name;                // name of the plugin
    public $is_source_code = true;      // format the message as source code?   
    public $source;                     // source line or message
    public $source_context;             // source code around the source line
    public $proposed_fix;               // proposed fix for the source line (if available)
    public $line_number = 0;            // line number within the source file
    public $description = "";           // description (long, html) of the item
    public $custom = array();           // for any other custom variables that a Plugin wants
}
?>