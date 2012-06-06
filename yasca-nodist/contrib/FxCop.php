<?php

/**  
 * The FxCop plugin is used to find potential vulnerabilities in .NET assemblies.
 * Only the security rules set is used.
 *
 * Created by Michael Maass 07/02/2009
 *
 * @extends Plugin  
 * @package Yasca  
 */  
class Plugin_FxCop extends Plugin {  
    public $valid_file_types = array("EXE", "DLL");  

    public $is_multi_target = true;

    public $installation_marker = true; 

    public $executable = array('Windows' => "%SA_HOME%resources\\utility\\FxCop\\FxCopCmd.exe",
	    'Linux' => "wine %SA_HOME%/resources/utility/FxCop/FxCopCmd.exe");
    public $arguments = array('Windows' => " /out:scan.xml /rule:%SA_HOME%resources\\utility\\FxCop\\Rules\\SecurityRules.dll /rule:%SA_HOME%resources\\utility\\FxCop\\Rules\\DesignRules.dll /iit /file:",
			'Linux' => " /out:scan.xml /rule:%SA_HOME%resources/utility/FxCop/Rules/SecurityRules.dll /rule:%SA_HOME%resources/utility/FxCop/Rules/DesignRules.dll /iit /file:");

   /**  
    * Executes the FxCop executable.
    */  
    function execute() {  
        static $alreadyExecuted;  
        if ($alreadyExecuted == 1) return;  
        $alreadyExecuted = 1;  
     
        $yasca =& Yasca::getInstance();  
        $dir = $yasca->options['dir'];  
        $stat_msgs = array();  

	$executable = $this->executable[getSystemOS()];
	$executable = $this->replaceExecutableStrings($executable);
	$arguments = $this->arguments[getSystemOS()];
	$arguments = $this->replaceExecutableStrings($arguments);

        if (getSystemOS() == "Windows" ||
            (getSystemOS() == "Linux" && !preg_match("/no wine in/", `which wine`))) {
            $yasca->log_message("Forking external process (FxCop)...", E_USER_WARNING);
            exec( $executable . $arguments . escapeshellarg($dir), $stat_msgs);
        } 
     
        if ($yasca->options['debug']) {  
            $yasca->log_message("FxCop returned: " . implode("\r\n", $stat_msgs), E_ALL);  
	}  

	if (!file_exists("scan.xml")) {
	    $yasca->log_message("External process completed...", E_USER_WARNING);
	    return;
	}

        $dom = new DOMDocument();  
        if (!$dom->load("scan.xml")) {  
            $yasca->log_message("FxCop did not return a valid XML document. Ignoring.", E_USER_WARNING);  
            return;  
        }  
     
	$yasca->log_message("External process completed...", E_USER_WARNING);

	//Process results
	foreach ($dom->getElementsByTagName("Namespace") as $namespace) {
	    if ($namespace->getElementsByTagName("Type")->length == 0)  
		$this->process_messages($namespace);	    
	}  

	foreach ($dom->getElementsByTagName("Module") as $module) {  
	    $this->process_messages($module);	    
	}  

	foreach ($dom->getElementsByTagName("Type") as $type) {
	    $this->process_messages($type);	    
	}  

	unlink("scan.xml");
    }  

    function process_messages($node) {
	foreach($node->getElementsByTagName("Message") as $message) {
	    $result = new Result();
	    $result->line_number = 0;
	    $result->filename = $node->getAttribute("Name");
	    $result->plugin_name = $message->getAttribute("TypeName");
	    $result->severity = $this->LevelToSeverity($message->getElementsByTagName("Issue")->item(0)->getAttribute("Level"));
	    $result->category = "FxCop Security Messages";
	    $result->source = $message->getAttribute("CheckId") . ": " . 
		    $message->getAttribute("TypeName") . " (" . 
		    $message->getAttribute("FixCategory") . ")";
	    $result->is_source_code = false;
	    $result->source_context = "";
	    $result->description = $message->getElementsByTagName("Issue")->item(0)->nodeValue;

	    array_push($this->result_list, $result);
	}
    }

    function LevelToSeverity($level) {
	if (strcasecmp($level, "Informational") == 0) {
	    return 5;
	} else if (strcasecmp($level, "Warning") == 0) {
	    return 4;
	} else if (strcasecmp($level, "CriticalWarning") == 0) {
	    return 3;
	} else if (strcasecmp($level, "Error") == 0) {
	    return 2;
	} else if (strcasecmp($level, "CriticalError") == 0) {
	    return 1;
	}

	return 5;
    }
}  
?> 
