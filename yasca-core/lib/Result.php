<?php
/**
 * Result Class
 *
 * This struct holds result information for a particular issue found. There will be
 * one Result object created for each such issue. 
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
final class Result {
	/**
	 *  Name of the file
	 *  @var string
	 */
    public $filename;
    
    /**
     * Informational. Default is 5.
     * Must be in the range 1-5, 5 being the least severe.
     * @var int
     */
    public $severity = 5;
    
    /**
     * Category of result. Default is "General".
     * @var string
     */
    public $category = "General";  

    /**
     * URL pointing to information about the category
     * @var string
     */
    public $category_link; 
    
    /**
     * Name of the plugin
     * @var string
     */
    public $plugin_name;        

    /**
     * Format the message as source code?
     * @var boolean
     */
    public $is_source_code = true;
      
    /**
     * Source line or message
     * @var string
     */
    public $source; 
    
    /**
     * Source code around the source line
     * @var string
     */
    public $source_context;
    /**
     * proposed fix for the source line (if available)
     * @var string
     */
    public $proposed_fix;      
    
    /**
     * Line number within the source file
     * @var int
     */
    
    public $line_number = 0;
    
    /**
     * Description (long, html) of the item
     * @var string
     */
    public $description = "";
    
    /**
     * For any other custom variables that a Plugin wants
     * @var mixed
     */
    public $custom = array();
}
?>