<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Wraps \proc_open and \proc_close to ensure that a process
 * is closed when no longer used.
 *
 * In addition, allows attaching a callback for when the process completes.
 *
 * (PNCTL libraries are not available on Windows as of PHP 5.4.3)
 *
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class Process {
	use Closeable;

	private static $maxStreamMemory = '';

	private $process;
	private $pipes;

	/**
	 * @param string $command
	 * @throws ProcessStartException
	 */
	public function __construct($command){
		//Create output and error streams that do not block after a certain size,
		//allowing the launched process to run to completion without waiting for
		//PHP to empty it's buffers.
		$stdoutTempStream = \fopen('php://temp' . self::$maxStreamMemory, 'rw');
		$stderrTempStream = \fopen('php://temp' . self::$maxStreamMemory, 'rw');
        $pipes = [];
        try {
			$this->process = \proc_open(
				$command,
				[
					0 => ['pipe', 'r',],
		          	1 => $stdoutTempStream,
		          	2 => $stderrTempStream,
		        ],
		        $pipes,
		        null,
		        null,
		        [
		        	//This switch only applies on Windows machines
		        	'bypass_shell' => true,
		        ]
		    );
        } catch (\ErrorException $e){
        	$pipes[1] = $stdoutTempStream;
		    $pipes[2] = $stderrTempStream;
		    $this->pipes = $pipes;

        	$matches = [];
        	$match = \preg_match('`(?<=CreateProcess failed, error code - )\d+`u', $e->getMessage(), $matches);
        	if ($match === 1){
				throw new ProcessStartException(
					'Unable to start process, Windows error ' . $matches[0] .
					'. See http://msdn.microsoft.com/en-us/library/ms681381.aspx'
				);
        	} else {
        		throw new ProcessStartException('Unable to start process');
        	}
        }

        $pipes[1] = $stdoutTempStream;
		$pipes[2] = $stderrTempStream;
		$this->pipes = $pipes;

	    if (\is_resource($this->process) !== true){
	    	//This will trigger the destructor, which will ensure the streams are closed
	    	throw new ProcessStartException('Unable to start process');
	    }
	}

	public function writeToStdin($content){
		\fwrite($this->pipes[0], $content);
		return $this;
	}

	public function closeStdin(){
		\fclose($this->pipes[0]);
		unset($this->pipes[0]);
		return $this;
	}

	private $alreadyBound = false;
	/**
	 * Creates an Async object that uses the provided callback when the process completes.
	 * @param callable $handler Params (string $stdout, string $stderr). Return value passed to Async.
	 * @throws \BadMethodCallException Multiple continuations are not supported by this version
	 * @return Async
	 */
	public function whenCompleted(callable $handler){
		if ($this->alreadyBound === true){
			throw new \BadMethodCallException('This version does not implement multiple continuations');
		}
		$this->alreadyBound = true;
		$stdout = '';
		$stderr = '';
		return new Async(
			//Instance anonymous function keeps this instance alive,
			//preventing PHP from closing the process.
			function() use (&$stdout, &$stderr){
				if (\proc_get_status($this->process)['running'] === true){
					return false;
        		}
        		\rewind($this->pipes[1]);
        		$stdout = \stream_get_contents($this->pipes[1]);
				\rewind($this->pipes[2]);
				$stderr = \stream_get_contents($this->pipes[2]);
				$this->close();
				return true;
        	},
        	static function() use (&$stdout, &$stderr, $handler){
        		return $handler($stdout, $stderr);
        	}
        );
	}

	protected function innerClose(){
		foreach($this->pipes as $pipe){
			\fclose($pipe);
		}

		if (\is_resource($this->process) === true){
			\proc_close($this->process);
		}
	}
}
\Closure::bind(
	static function(){
		//Allow up to 12 megabytes to be stored in RAM before going to disk.
		$megs = 12;
		$max = $megs * 1024 * 1024;
		static::$maxStreamMemory = "/maxmemory:$max";
	},
	null,
	__NAMESPACE__ . '\\' . \basename(__FILE__, '.php')
)->__invoke();