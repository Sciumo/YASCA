<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * An emulation of asynchonous tasks/threads found in other platforms, such as Python.
 * Because all "async" calls are within one PHP thread, it is best used when
 * spawning and monitoring processes or network connections while executing
 * other PHP code. (PNCTL libraries are not available on Windows as of PHP 5.4.3)
 *
 * Two methods of interest: $this->isDone() and $this->getResult() (blocking).
 *
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class Async {
	use CallablePropertiesAsMethods;

	private static $tickables;

	/**
	 * @return bool
	 */
	public static function any(){
		return Iterators::any(self::$tickables);
	}

	/**
	 * For each async tasks scheduled, execute its pulse function synchronously
	 * Do not use with declare(ticks=...) and register_tick_function
	 * as that feature is deprecated as of PHP 5.3.0.
	 * Instead, consider making a call for each event loop at the top level in your script.
	 */
	public static function tick(){
		//tickables() are allowed to register new tickables
		//Make sure that these are not lost.
		$snapshotOfTickables = self::$tickables;
		self::$tickables = new \SplDoublyLinkedList();
		self::$tickables =
			(new \Yasca\Core\IteratorBuilder)
			->from($snapshotOfTickables)
			->where(static function($tickable){
				return $tickable() !== true;
			})
			->concat(self::$tickables)
			->toList();
	}

	/**
	 * Creates a new Async class with the checker ($isDone) and the finisher ($getResultWhenDone)
	 * Exceptions thrown on $isDone are caught and rethrown on $this->getResult().
	 * @param callable $isDone No parameters. Returns true|false.
	 * @param callable $getResultWhenDone No parameters. Returns result of async operation.
	 */
	public function __construct(callable $isDone, callable $getResultWhenDone){
		$done = false;
		$retval = null;
		$this->isDone = static function() use (&$done){return $done;};
		$this->getResult = static function() use (&$done, &$retval){
			if (!$done){
				//Busy loop for a short time, then fall back to sleep(1)
				for($i = 0; $i < 50; $i++){
					self::tick();
					if ($done){
						break;
					}
				}
				while(!$done){
					\sleep(1);
					self::tick();
				}
			}
			return $retval;
		};

		$tickable = function() use ($isDone, $getResultWhenDone, &$done, &$retval){
			try {
				$done = $isDone();
				if ($done){
					$retval = $getResultWhenDone();
				}
				return $done;
			} catch (\Exception $e){
				$this->isDone = static function(){return true;};
				$this->getResult = static function() use ($e){throw $e;};
				return true;
			}
		};

		if ($tickable() !== true){
			self::$tickables->push($tickable);
		}
	}
}
\Closure::bind(
	static function(){
		static::$tickables = new \SplDoublyLinkedList();
	},
	null,
	__NAMESPACE__ . '\\' . \basename(__FILE__, '.php')
)->__invoke();