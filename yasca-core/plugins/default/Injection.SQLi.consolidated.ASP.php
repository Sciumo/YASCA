<?php

/**
 * This class looks for SQLi vulnerabilities in the form:
 *
 *	test1 = Request.form("blah");
 *	test2 = Request("blah");
 *	test3 = Request.QueryString("blah");
 *	test4 = Session("blah");
 *
 *	"SELECT * where" & test1
 *	"INSERT * where" & test2
 *	"UDPATE * where" & test3
 *	"DELETE * where" & test4
 *	test4 & "DELETE * where" & test4
 *
 * @extends Plugin
 * @package Yasca
 */
class Plugin_injection_sqli_asp extends Plugin {
    public $valid_file_types = array("asp");
    
    function execute() {
		$SOURCE=0; // constants for indexing sqli_array
		$SINK=1;
		$sqli_array=array(); //Array to store the sources/sinks of the SQLi problems
		$line_numbers=array();

		$count = count($this->file_contents);
        for ($i=0; $i<$count; $i++) {

           if (preg_match('/([a-zA-Z0-9\_]+)\s*\=\s*(Request(\.form\(\s*\"(.*)\"\s*\)|\.QueryString\(\s*\"(.*)\"\s*\)|\(\s*\"(.*)\"\s*\))|Session\(\s*\"(.*)\"\s*\))/i', $this->file_contents[$i], $matches)) {
                $variable_name = $matches[1];
                $parameter_name = $matches[2];
                
                for ($j=$i+1; $j<$count; $j++) {

                    if (preg_match('/(update|insert|delete|select)[^\+\;]*\&\s*' . $variable_name . '\s*\&*/i', $this->file_contents[$j])) {
			if ( !isset($sqli_array[$variable_name][$SOURCE]) )
			{
			    $sqli_array[$variable_name][$SOURCE]="";
			    $sqli_array[$variable_name][$SINK]="";
			}
			if (!stristr( $sqli_array[$variable_name][$SINK], "".($j+1) ) )
			{
			    $sqli_array[$variable_name][$SINK]=$sqli_array[$variable_name][$SINK]." ".($j+1);
			}
			if (!stristr( $sqli_array[$variable_name][$SOURCE], "".($i+1) ) )
			{
			    $sqli_array[$variable_name][$SOURCE]=$sqli_array[$variable_name][$SOURCE]." ".($i+1);
			}
			$line_numbers[$variable_name]=$j+1;
            continue;
                    }
                }
            }
        }

		foreach( $sqli_array as $key=>$value)
		{	
		    $result = new Result();
		    $result->plugin_name = "Sink/Source SQL Injection"; 
		    $result->line_number = $line_numbers[$key];
		    $result->severity = 1;
		    $result->category = "Possible SQL Injection";
		    $result->category_link = "http://en.wikipedia.org/wiki/SQL_injection";
		    $result->description = <<<END
    <p>
 	Source lines are: $value[0]<br/>
	Sink lines are: $value[1]
    </p>   
    <p>
	<h4>SQL Injection</h4>
	SQL injection is a code injection technique that exploits a security vulnerability 
	occurring in the database layer of an application. The vulnerability is present when 
	user input is either incorrectly filtered for string literal escape characters 
	embedded in SQL statements or user input is not strongly typed and thereby unexpectedly 
	executed. It is an instance of a more general class of vulnerabilities that can occur 
	whenever one programming or scripting language is embedded inside another. SQL injection 
	attacks are also known as SQL insertion attacks.
    </p>	
    <p>
	<h4>References</h4>
	    <ul>
		<li><a href="http://en.wikipedia.org/wiki/SQL_injection">Wikipedia: SQL Injection</a></li>
	    </ul>
    </p>
END;

		    array_push($this->result_list, $result);                
		}
    }
}
?>
