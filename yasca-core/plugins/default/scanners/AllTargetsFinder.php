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
	protected static $CACHE_ID = 'Plugin_AllTargetsFinder.target_list,All Scanned Files';

	/**
	 * This plugin is multi-target, only run once.
	 */
	public $is_multi_target = true;
	
	protected static $already_executed = false;
	
	public function Plugin_AllTargetsFinder($filename, &$file_contents){
		if (self::$already_executed){
			$this->initialized = true;
			return;
		}
		parent::Plugin($filename, $file_contents);
	}

	/**
	 * Executes this plugin, scanning for files, placing them into an attachment.
	 */
	function execute() {
    	if (self::$already_executed) return;
    	self::$already_executed = true;
    	
		$yasca =& Yasca::getInstance();
		$yasca->general_cache[self::$CACHE_ID] =
			array_map(function ($target) use ($yasca) {
					return str_replace($yasca->options['dir'], "", correct_slashes($target));
				}
				,$yasca->target_list);
		$yasca->add_attachment(self::$CACHE_ID);
	}

}
?>