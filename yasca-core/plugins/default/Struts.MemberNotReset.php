<?php
//@TODO FINISH THIS FUNCITON
/**
 * This class looks for ActionMapping classes that do not reset all member variables.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_struts_membernotreset extends Plugin {
    public $valid_file_types = array("java");

    function execute() {
        if (!preg_grep("/extends ActionForm/", $this->file_contents)) {
            return;
        }
        
        $matches = array();
        $member_vars = find_member_variables($this->file_contents, $matches);

        $reset_function = get_method_contents($this->file_contents, "reset");
        
        if (count($reset_function) == 0) {  /* No reset function == OK (but odd) TODO Is this an error anyway? */
            return;
        }

        foreach ($member_vars as $member) {
            foreach ($reset_function as $line) {
                if (preg_match('/' . $member . '\s*=/i', $line)) {
                    unset($member_vars[array_search($member, $member_vars)]);
                }
            }
        }
        foreach ($member_vars as $line_number => $member) {
            $result = new Result();
            $result->plugin_name = "Struts: Member Variable Not Reset";
            $result->line_number = $line_number+1;
            $result->severity = 2;
            $result->category = "Struts: Member Variable Not Reset in ActionForm.reset() Function";
            $result->description = <<<END
            <p>
                The ActionForm.reset() function should be used to reset all member variables. This is because
                ActionForm objects are singletons that are not used for multiple requests, and for multiple
                users. The job of the reset() function is to reset the object state back to "zero" so that
                subsequent requests do not reference incorrect data.
            </p>
            <p>
                <h4>References</h4>
                <ul>
                    <li><a href="http://struts.apache.org/1.x/userGuide/building_controller.html">Struts User Guide</a></li>
                </ul>
            </p>
END;
            
            array_push($this->result_list, $result);
        }
    }
}
?>
