$in_sql = 0;
$out = "";
$line = "";

while(<STDIN>) {
    $line = $_;
    if ($line =~ /sql\s*=\s*\"$/) {
	$in_sql = 1;
	print "Got a sql\n";
    }
    if ($in_sql == 1) {
	if ($line =~ /\"\>/) {
	    $in_sql = 0;
	}
        if ($in_sql == 1) {
	    $line =~ s/[\t\r\n]//g;
	    $line =~ s/^\s*/ /g;
	    $line =~ s/\s*$/ /g;
	    $line =~ s/\s+/ /g;
	}
    }
    $out .= $line;
}
print $out;