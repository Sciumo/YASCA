class A {

    public void foo(HttpServletResponse response) {
	Cookie c = new Cookie("isAdmin", "1");
	response.addCookie(c);
    }

}