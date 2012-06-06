<? //

// basic xss test case


$s = $_GET["foo"];
$s .= "bar";
if ($s !== "bee") {
    f($s);
}

function f($m) {
    print $m;
}

?>
<? //


// $PHP_SELF (and other harmless server vars) was only modelled
// together with SERVER; led to false positives




echo $PHP_SELF;     // dangerous!
echo $_SERVER['PHP_SELF'];  // dangerous!
echo $HTTP_SERVER_VARS['PHP_SELF'];         // dangerous!

echo $_SERVER['EVIL'];      // dangerous!



?>
<? //

// printf is a builtin function, but a sink for XSS analysis;
// check if it is correctly detected, and not lost inside a basic block




$a = 1;
$b = md5($evil);
$c = trim($evil);
$d = 1;
printf($c);     // vuln
$e = 1;
md5($c);




?>
<? //

if ($get) {
    $x = 'a' . 'b';
} else {
    $x = 'c' . 'd';
}
mysql_query($x);






?>
<? //

// unmodeled builtin functions

$a = 'x';
$a = trim($a);
mysql_query($a);






?>
