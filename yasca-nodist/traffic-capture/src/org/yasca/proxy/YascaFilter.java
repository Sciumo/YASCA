// Copyright (C) 2010 Michael V. Scovetta

// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
// FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
// COPYRIGHT HOLDERS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
// SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
// STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
// ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
// OF THE POSSIBILITY OF SUCH DAMAGE.

// FOR MORE INFORMATION, REFER TO THE YASCA LICENSE AGREEMENT.

package org.yasca.proxy;

import java.io.PrintWriter;
import net.grinder.common.Logger;
import net.grinder.tools.tcpproxy.*;

/**
 * TCPProxy filter used to capture interesting content for Yasca to scan.
 * 
 * @author Michael Scovetta <scovetta@users.sourceforge.net> {@link}
 *         http://www.yasca.org
 */
public class YascaFilter implements TCPProxyFilter {
	
	public static void main(String[] args) {
		args = new String[] { "-requestfilter", "org.yasca.proxy.YascaFilter", "-responsefilter", "org.yasca.proxy.YascaFilter" };
		net.grinder.TCPProxy.main(args);
	}
	/** The output handle */
	private final PrintWriter output;

	/**
	 * Create a new YascaFilter object.
	 * @param logger Logger to use.
	 */
	public YascaFilter(Logger logger) {
		output = logger.getOutputLogWriter();
	}

	/**
	 * Handle a message fragment from the stream.
	 * 
	 * @param connectionDetails Describes the connection.
	 * @param buffer Contains the data.
	 * @param bytesRead How many bytes of data in <code>buffer</code>.
	 * @return Filters can optionally return a <code>byte[]</code> which will be
	 *         transmitted to the server instead of <code>buffer</code>.
	 * @throws FilterException If an error occurs.
	 */
	public byte[] handle(ConnectionDetails connectionDetails, byte[] buffer, int bytesRead) throws FilterException {
		final StringBuffer stringBuffer = new StringBuffer();

		for (int i = 0; i < bytesRead; i++) {
			final int value = buffer[i] & 0xFF;

			// If it's ASCII, print it as a char.
			if (value == '\r' || value == '\n' || (value >= ' ' && value <= '~')) {
				stringBuffer.append( (char)value );
			} else {
				String s = "%";
				if (value <= 0xf) s += "0";
				s += Integer.toHexString(value).toUpperCase();
				stringBuffer.append(s);
			}
		}

		output.println("[CONNECTION]: " + connectionDetails);
		output.println(stringBuffer);
		output.println();

		return null;
	}

	/**
	 * A new connection has been opened.
	 * 
	 * @param connectionDetails Describes the connection.
	 */
	public void connectionOpened(ConnectionDetails connectionDetails) {
	}

	/**
	 * A connection has been closed.
	 * 
	 * @param connectionDetails Describes the connection.
	 */
	public void connectionClosed(ConnectionDetails connectionDetails) {
	}
}
