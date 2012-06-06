#!/usr/bin/perl

# Changes CFML to something that Yasca can parse.

@file = <STDIN>;

for ($i=0; $i<scalar @file; $i++) {
	$line = @file[$i];
	$line =~ s/[\r\n]//;
	$j = 0;
	while ($line =~ /\s*\<[^\>]*\s*$/ && $j++ < 20) {
		$nextLine = @file[$i+$j];
		$nextLine =~ s/^\s*//;
		$nextLine =~ s/\s*$//;
	
		$line .= $nextLine;

	}
	$i += $j;
	$line =~ s/\>/\>\n/g;

	$line =~ s/^\s+//;
	$line =~ s/\s+$//;
	$line =~ s/\n\s+/\n/m;

	print "$line\n";
}
