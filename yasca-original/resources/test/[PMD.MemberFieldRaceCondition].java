import java.io.*;
import javax.servlet.*;
import javax.servlet.http.*;

class AServlet extends HttpServlet {
    public int foo = null;

    public void service(HttpServletRequest request, HttpServletResponse response) throws ServletException {
	++foo;
    
	PrintWriter out = response.getWriter();
	out.setContentType("text/html");
	out.println(foo);
    }
}