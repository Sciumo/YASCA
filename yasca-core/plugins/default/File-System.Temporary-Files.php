<?php
require_once("lib/common.php");
require_once("lib/Plugin.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");
/**
 * This class looks for temporary files.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_file_system_temporary_files extends Plugin {
    public $valid_file_types = array(); // match everything
    
    protected static $already_executed = false;

    public $is_multi_target = true;

    public function __construct($filename, $file_contents) {
        if (static::$already_executed) {
        	$this->initialized = true;
        	return;
        }
        
        parent::__construct($filename, $file_contents);
    }
    
    public function execute() {        
        if (static::$already_executed) return;
        static::$already_executed = true;
    	
    	$match_once_patterns = array(
        	"vssver.scc"	=> "Visual SourceSafe",
    	    "*.pdb"			=> "Visual Studio Debug ",
            "thumbs.db"		=> "Windows Explorer Thumbnail",
            "*.psd"			=> "Raw Photoshop", 
            "hco.log"		=> "CA Harvest Log", 
            "harvest.sig"	=> "CA Harvest signature",
            "*.svn-base"	=> "SVN",
            "all-wcprops"	=> "SVN",
    		"*.cvsignore"	=> "CVS",
            ".project"		=> "Eclipse",
    		".classpath"	=> "Eclipse",
    		".buildpath"	=> "Eclipse",
    		"org.eclipse.*prefs" => "Eclipse",
            ".gitignore"    => "Git"
        );
                            
        $match_multiple_patterns = array(
            "*.suo"			=> "Visual Studio",
    		"*.sln"			=> "Visual Studio",
    		"*.csproj"		=> "Visual Studio",
    		"*.csproj.user" => "Visual Studio",
            "*.tmp"			=> "Temporary",
            "*.temp"		=> "Temporary",
            "*dummy*"		=> "Temporary",
            "*.old"			=> "Backup",
            "*.bak"			=> "Backup",
            "*.save"		=> "Backup",
            "*.backup"		=> "Backup",
            "*.orig"		=> "Backup",
            "*.000"			=> "Temporary",
        	"temp.*" 		=> "Temporary",
            "*.copy"		=> "Copy/paste",
            "Copy of*"		=> "Windows XP and earlier copy/paste",
    		"* - Copy"	 	=> "Windows Vista and newer copy/paste",
            "_*"			=> "Temporary"	//@todo What systems or methods use this to denote temp files?
        );
        	
        $exception_list = array(
        	"__*__.py" /* Do not match special python files */
        );
		
    	$yasca =& Yasca::getInstance();
    	$target_basename_list = array_unique(
    		array_map(function ($target){return basename($target);}, $yasca->target_list)
    	); 
    	
    	$found_items = array();
    	foreach ($target_basename_list as $target){
    		foreach($exception_list as $pattern){
    			if (fnmatch($pattern, $target)) continue 2;
    		}
    		foreach($match_once_patterns as $pattern => $description){
    			if (fnmatch($pattern, $target)){
    				$found_items[$pattern] = $description;
    				continue 2;
    			}
    		}
		foreach($match_multiple_patterns as $pattern => $description){
		    if (fnmatch($pattern, $target)){
    				$found_items[$target] = $description;
    				continue 2;
    			}
    		}
    	}
    	    		
    	foreach($found_items as $item => $description){
	        $result = new Result();
            $result->filename = $item;
            $result->severity = 3;
            $result->plugin_name = "Potentially Sensitive Data Visible";
            $result->category = "Potentially Sensitive Data Visible";
            $result->category_link = "http://www.owasp.org/index.php/Sensitive_Data_Under_Web_Root";
            $result->description = <<<END
<p>
        Temporary, backup, or hidden files should not be included in a production site because they can sometimes contain
    sensitive data such as:
        <ul>
	        <li>Source Code (e.g. index.php.old)</li>
	        <li>A list of other files (e.g. harvest.sig, .svn/*)</li>
	        <li>Deployment information (e.g. .project)</li>
        </ul>
        These files should be removed from the source tree, or at least prior to a production rollout.
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href="https://www.owasp.org/index.php/Guessed_or_visible_temporary_file">OWASP: Guessed or Visible Temporary File</a>
        </ul>
</p>
END;
            $result->source = "$description files should not be visible on a production site.";
            $result->is_source_code = false;
            $this->result_list[] = $result;
        }
    }
}
?>
