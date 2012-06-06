<?php

/**
 * This class looks for all scanned files, placing them in an attachment.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_AllTargetsFinder extends Plugin {
    public $valid_file_types = array();
    
    /**
     * Unique ID used by this plugin to refer to the general cache.
     */
    private static $CACHE_ID = 'Plugin_AllTargetsFinder.target_list,All Scanned Files';
    
    /**
     * This plugin is multi-target, only run once.
     */
    public $is_multi_target = true;
    
    /**
     * Executes this plugin, scanning for files, placing them into an attachment.
     */
    function execute() {
        $yasca =& Yasca::getInstance();
        $yasca->general_cache[Plugin_AllTargetsFinder::$CACHE_ID] = $yasca->target_list;
        $yasca->add_attachment(Plugin_AllTargetsFinder::$CACHE_ID);
    }
    
}
?>