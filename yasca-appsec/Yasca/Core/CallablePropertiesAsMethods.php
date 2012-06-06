<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * PHP 5.4.3 does not support calling callable properties as methods
 * This trait allows simulating that support. Remove if/when a future PHP
 * version supports this natively.
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
trait CallablePropertiesAsMethods{
	private static function ☂call(callable $f, array $arguments){
		//\call_user_func() is expensive. Avoid for most calls.
		//As of PHP 5.4.3, switch does not use strict equality
		$argCount = \count($arguments);
		if ($argCount === 0){
			return $f();
		} elseif ($argCount === 1){
			return $f($arguments[0]);
		} elseif ($argCount === 2){
			return $f($arguments[0], $arguments[1]);
		} elseif ($argCount === 3){
			return $f($arguments[0], $arguments[1], $arguments[2]);
		} else {
			return \call_user_func_array($f, $arguments);
		}
	}

	public function __call($name, array $arguments){
		try{
			$f = $this->$name;
		} catch (\ErrorException $e){
			//Workaround for lack-of-fix for https://bugs.php.net/bug.php?id=51176
			//Calling a function statically from a non-static method on the same class
			//will instead call the function non-statically.
			if (0 === \mb_strpos($e->getMessage(), 'Accessing static property')){
				$f = static::$$name;
			} else {
				throw $e;
			}
		}
		return self::☂call($f, $arguments);
	}

	public static function __callStatic($name, array $arguments){
		return self::☂call(static::$$name, $arguments);
	}
}