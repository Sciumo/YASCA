<?php

/**
 * This plugin registers a callback to Yasca to run after the scan.
 * It appends a basic summary grid as an attachment to the report.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_BasicSummaryGrid extends Plugin {
    public $valid_file_types = array();
    
    protected static $CACHE_ID = 'Plugin_BasicSummaryGrid.top_finding,Basic Summary Grid';
    
    public $is_multi_target = true;
    
    function execute() {
    	static $already_added = false;
    	if ($already_added) return;
    	$already_added = true;
    	
        $yasca =& Yasca::getInstance();
            
        $yasca->general_cache[self::$CACHE_ID] = "";
        $yasca->add_attachment(self::$CACHE_ID);
        
        $id = self::$CACHE_ID;
        $yasca->register_callback('post-scan', function () use ($id) {
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
	        $yasca->general_cache[$id] = $html;
	    });
    }
}
?>
