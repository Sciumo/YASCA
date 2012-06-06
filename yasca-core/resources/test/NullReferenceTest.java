public class NullReferenceTest {
    public void printMessage(String msg) {
	System.out.println( "Length = " + (msg != null ? new String(msg) : null).length() );
	
    }
}
