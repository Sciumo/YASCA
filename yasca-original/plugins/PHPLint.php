<?php

/**
 * The PHPLint Plugin uses PHPLint to discover potential vulnerabilities in .php files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 *
 * Special thanks to Jochen Paul for writing this plug-in.
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_PHPLint extends Plugin {
    public $valid_file_types = array("php", "php4", "php5");

    public $is_multi_target = false;
    
    private $executable = array('Windows' => "resources\\utility\\phplint\\phplint.bat",
                                'Linux'   => "sh ./resources/utility/phplint/phplint.sh");
   
   /**
    * Executes PHPLint on each file.
    */
    function execute() {

        $yasca =& Yasca::getInstance();
        $dir = $yasca->options['dir'];      

        $yasca->log_message("Forking external process (PHPLint)...", E_ALL);
        
        $result_list = array();

        if (getSystemOS() == "Windows") {
            $filename = $this->filename;
            $filename = str_replace(":", "", $filename);
            $filename = "/cygdrive/" . $filename;
        } else {    
            $filename = $this->filename;        // Linux
        }
            
        exec( $this->executable[getSystemOS()] . " " . escapeshellarg($filename),  $result_list);

        if ($yasca->options['debug'])
            $yasca->log_message("PHPLint returned: " . implode("\r\n", $result_list), E_ALL);
            
         // Now check each message
         foreach($result_list as $result) {
            // http://www.txt2re.com/
            $re1='((?:\\/[\\w\\.\\-]+)+)';  # Filename
            $re2='.*?';                     # Non-greedy match on filler
            $re3='(\\d+)';                  # Linenumber
            $re4='.*?';                     # Non-greedy match on filler
            $re5='((?:[a-z][a-z]+))';       # Category
            $re6='.*?';                     # Non-greedy match on filler
            $re7=':\s(.*)';                 # Errormessage
            
            // Does the message fit to our regular expression?
            if (preg_match_all("/".$re1.$re2.$re3.$re4.$re5.$re6.$re7."/is", $result, $matches)) {              
                // Retrieve the values
                $filename = $matches[1][0];
                $line_number = is_numeric($matches[2][0]) ? $matches[2][0] : 0;
                $severity = $matches[3][0];
                $message = $matches[4][0];
                
                $description = <<<END
<p>
        This finding was discoverd by PHPLint and is titled:<br/>
        <div style="margin-left:10px;"><strong>{$matches[4][0]}</strong></div>
</p>
<p>
        <h4>References</h4>
        <ul>
                <li><a href="http://www.icosaedro.it/phplint/">PHPLint Home Page</a></li>
        </ul>
</p>
END;
                if (strlen($message) > 50) {
                    $message = explode(". ", $message);
                    $message = $message[0];             // just take the first sentence
                }

                $result = new Result();
                $result->line_number = $line_number;
                $result->filename = $this->filename;
                $result->plugin_name = $yasca->get_adjusted_alternate_name("PHPLint", $message, $message);
                $result->severity = $yasca->get_adjusted_severity("PHPLint", $severity, 5);
                $result->category = "PHPLint Finding";
                $result->category_link = "http://www.icosaedro.it/phplint/";
                $result->source = $yasca->get_adjusted_description("PHPLint", $message, $message);
                $result->is_source_code = false;
                
                if (file_exists($filename) && is_readable($filename)) {
                    $t_file = @file($filename);
                    if ($t_file != false && is_array($t_file)) {
                        $result->source_context = array_slice( $t_file, max( $result->line_number-(($this->context_size+1)/2), 0), $this->context_size );
                    }
                } else {
                    $result->source_context = "";
                }
                
                $result->description = $description;
                array_push($this->result_list, $result);
            }
        }
        $yasca->log_message("External process completed...", E_ALL);
    }   
}
?>
