
/* From http://cwe.mitre.org/data/definitions/416.html */
char* ptr = (char*)malloc (SIZE);
if (err) {

	abrt = 1;
	free(ptr);
}

foo();
bar();
quux();

if (abrt) {
	logError("operation aborted before commit", ptr);
}