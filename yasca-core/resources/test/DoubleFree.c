
void getFoo() {

	// blah blah blah
	int* i = malloc(100);
	while(1) {
	}

	free(i);
	foobar();
	free( i );


	free( i );

	if(0) {
		free(i);
	}

}