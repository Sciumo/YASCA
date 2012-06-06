<?php

/**
 * This plugin creates a basic summary grids and adds it as an attachment to the report.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_BasicSummaryGrid extends Plugin {
    public $valid_file_types = array();
    public $invalid_file_types = array("jar", "exe", "zip", "war", "tar", "ear", "lib", "dll");
    
    private static $CACHE_ID = 'Plugin_BasicSummaryGrid.top_finding,Basic Summary Grid';
    
    function execute() {
        $yasca =& Yasca::getInstance();
            
        $yasca->general_cache[Plugin_BasicSummaryGrid::$CACHE_ID] = "";
        $yasca->add_attachment(Plugin_BasicSummaryGrid::$CACHE_ID);
                
        $yasca->register_callback('post-scan', array(get_class($this), 'report_callback'));
    }
    
    /**
     * Callback function for turning the URL list into an HTML table.
     */
    public static function report_callback() {
        $yasca =& Yasca::getInstance(); 

        $category_list = array();
        $file_list = array();
        
        foreach ($yasca->results as $result) {
            $heatmap[$result->category . "/" . $result->filename] = 1;
            array_push($category_list, $result->category);
            array_push($file_list, $result->filename);
        }
        $category_list = array_unique($category_list);
        $file_list = array_unique($file_list);

        $html = "<table style=\"width:auto;\">";
        $html .= "<thead><th>Filename</th>";
        foreach ($category_list as $category) {
            $html .= "<th title=\"$category\" style=\"width: 10px; cursor:hand;cursor:pointer;\" onclick=\"document.location.href='#" . md5($category) . "';\">*</th>";
        }
        $html .= "</thead>";

        foreach ($file_list as $filename) {
            $html .= "<tr>";
            $html .= "<td style=\"font-weight:bold;\">" . basename($filename) . "</td>";
            foreach ($category_list as $category) {
                $html .= "<td>";
                
                $html .= array_key_exists($category . "/" . $filename, $heatmap) ? "X" : "&nbsp;";
                $html .= "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";
        $yasca->general_cache[Plugin_BasicSummaryGrid::$CACHE_ID] = $html;  
    }
}
?>
