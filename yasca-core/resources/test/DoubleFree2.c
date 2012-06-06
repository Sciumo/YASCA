
void getFoo() {

	// blah blah blah
	int* i = malloc(100);

	if(0) {
		free(i);
	}
	free(i);

}