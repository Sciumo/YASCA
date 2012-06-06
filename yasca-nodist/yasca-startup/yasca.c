#include <stdio.h>
#include <stdlib.h>
#include <string.h>

int main(int argc, char* argv[]) {
	const char* cmd = ".\\resources\\php.exe -c resources/php.ini -q yasca.php %s";
	char* arg_buffer;
	char* cmd_buffer;
	int i=0, cmd_len = 1024;	// extra space

	// get the length of the buffer we'll need
	for (i=1; i<argc; i++) {
		cmd_len += strlen(argv[i]) + 3;
	}

	// allocate both buffers
	arg_buffer = (char*)malloc(cmd_len * sizeof(char));
	cmd_buffer = (char*)malloc((cmd_len + strlen(cmd)) * sizeof(char));
	arg_buffer[0] = '\0';

	// dump the arguments into the buffer
	for (i=1; i<argc; i++) {
		strcat( arg_buffer, "\"" );
		strcat( arg_buffer, argv[i] );
		strcat( arg_buffer, "\"" );
		strcat( arg_buffer, " " );
	}

	// create the final command string
	sprintf(cmd_buffer, cmd, arg_buffer);

	system(cmd_buffer);

	return 0;
}
