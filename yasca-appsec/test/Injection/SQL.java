
CallableStatement cs = con.prepareCall("{call SP_StoredProcedure " + request.getParameter("foo") + "}");

CallableStatement cs = con.prepareCall("{call SP_StoredProcedure " + foo + "}");