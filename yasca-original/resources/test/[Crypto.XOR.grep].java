class A {

    public String xor_encrypt(String s) {
	char[] k = new char[] { 'p', 'a', 's', 's' };
	String e = "";
	for (int i=0; i<s.length; i++) {
	    e += s.charAt(i) ^ k[i % k.length];
	}
	return e;
    }

}