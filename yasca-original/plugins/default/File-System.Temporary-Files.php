<?php

/**
 * This class looks for temporary files.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_file_system_temporary_files extends Plugin {
    public $valid_file_types = array(); // match everything
    
    public $pattern_list = array(
                "*.tmp",
                            "*.temp",
                            "*dummy*",
                            "*.old",
                            "*.bak",
                            "*.save",
                            "*.backup",
                            "*.orig",
                            "*.000",
                            "*.copy",
                                "temp.*",
                            "Copy of*",     /* Windows copy/paste */
                            "_*",
                            "vssver.scc",   /* Visual SourceSafe */
                            "thumbs.db",    /* Explorer Thumbnails */
                            "*.psd",    /* Photoshop */ 
                "hco.log",  /* CA Harvest */
                "harvest.sig",  /* CA Harvest */
                "*.svn-base",   /* SVN */
                "all-wcprops",  /* SVN */
                ".project", /* Eclipse */
                ".classpath",   /* Eclipse */
                ".gitignore"    /* Git */
                            );

    function execute() {        
        $filename = basename($this->filename);
        foreach ($this->pattern_list as $pattern) {
            if (fnmatch($pattern, $filename)) {
                $result = new Result();
                $result->severity = 3;
                $result->category = "Potentially Sensitive Data Under Web Root";
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
                $result->source = "This type of file is usually not used in production.";
                $result->is_source_code = false;
                array_push($this->result_list, $result);
            }
        }
        
        
    }
}
?>
