<?php
/**
 * This class looks for ActionMapping classes that do not have reset() functions.
 * @extends Plugin
 * @package Yasca
 */
class Plugin_struts_noresetfunction extends Plugin {
    public $valid_file_types = array("java");

    function execute() {
        if (!preg_grep("/extends ActionForm/", $this->file_contents)) {
            return;
        }
        
        $matches = array();
        $reset_function = get_method_contents($this->file_contents, "reset");
        $line_number = 0; //@TODO Determine how to calculate this.
        
        if (count($reset_function) == 0) {
            $result = new Result();
            $result->line_number = $line_number+1;
            $result->severity = 3;
            $result->category = "Struts: ActionForm.reset() function does not exist";
            $result->description = <<<END
            <p>
                The ActionForm.reset() function should be used to reset all member variables. Without
                this function, member variable may contain other users' data. This depends on the threading
                model used by the servlet container, but in general, the reset() function should be reset
                the object back to "zero" state.
            </p>
            <p>
                <h4>References</h4>
                <ul>
                    <li>TODO</li>
                </ul>
            </p>
END;

            array_push($this->result_list, $result);
        }
    }
}
?>
