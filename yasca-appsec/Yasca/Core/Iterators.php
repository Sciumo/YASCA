<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Provides common interfaces for PHP's multiple collection types.
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class Iterators {
	private function __construct(){}

	/**
	 * Returns true if there are any elements in $values; false otherwise
	 * @param unknown_type $values Any foreach-able object or value
	 * @param callable $selector Params ($value, $key). Returns true iff the item is selected.
	 */
	public static function any($values, callable $selector = null){
		if ($selector === null){
			foreach($values as $unused){
				return true;
			}
			return false;
		} else {
			foreach($values as $key => $value){
				if ($selector($value, $key) === true){
					return true;
				}
			}
			return false;
		}
	}

	/**
	 * Determines if the $values contains $value using strict equality (===).
	 * This will iterate through iterators.
	 * See http://us.php.net/manual/en/function.in-array.php
	 * See http://us.php.net/manual/en/splobjectstorage.contains.php
	 * @param unknown_type $values Any foreach-able object or value
	 * @param unknown_type $value
	 */
	public static function contains($values, $value){
		if (\is_array($values) === true){
			return \in_array($value, $values, true);
		} elseif($values instanceof \SplObjectStorage){
			return $values->contains($value);
		} else {
			foreach($values as $v){
				if ($v === $value){
					return true;
				}
			}
			return false;
		}
	}

	/**
	 * Counts the number of items that would show up in a foreach loop
	 * @param unknown_type $values Any foreach-able object or value
	 */
	public static function count($values){
		if (\is_array($values) === true){
			return \count($values);
		} elseif ($values instanceof \Countable){
			return $values->count();
		} elseif ($values instanceof \Iterator){
			return \iterator_count($values);
		} elseif ($values instanceof \IteratorAggregate){
			$iter = $values->getIterator();
			return \iterator_count($iter);
		} else {
			$count = 0;
			foreach($values as $unused){
				$count++;
			}
			return $count;
		}
	}

	/**
	 * Gets the value in the provided $values for the given $key,
     *  or throws IteratorException('Key not found in collection')
	 * Uses strict equality comparison
	 * @param unknown_type $values Any foreach-able object or value
	 * @param unknown_type $key
	 * @return unknown_type|NULL The value, or null if the value is not at that key
	 */
	public static function elementAt($values, $key){
		if (\is_array($values) === true || $values instanceof \ArrayAccess){
			if (isset($values[$key]) === true){
				return $values[$key];
			}
		} elseif ($values === null){
			//Pass through to throw statement
		} else {
			foreach($values as $key2 => $value){
				if ($key === $key2){
					return $value;
				}
			}
		}
		throw new IteratorException('Key not found in collection');
	}

	/**
	 * Gets the value in the provided $values for the given $key, or null if that key is not present.
	 * Uses strict equality comparison
	 * @param unknown_type $values Any foreach-able object or value
	 * @param unknown_type $key
	 * @return unknown_type|NULL The value, or null if the value is not at that key
	 */
	public static function elementAtOrNull($values, $key){
		if (\is_array($values) === true || $values instanceof \ArrayAccess){
			if (isset($values[$key]) === true){
				return $values[$key];
			} else {
				return null;
			}
		} elseif ($values === null){
			return null;
		} else {
			foreach($values as $key2 => $value){
				if ($key === $key2){
					return $value;
				}
			}
			return null;
		}
	}

	/**
	 * Wrap the passed in value as necessary to ensure an Iterator is returned.
	 * If the value is not an Iterator, Traversable, or an array, an EmptyIterator is returned.
	 * @param unknown_type $values
	 */
	public static function ensureIsIterator($values){
		//TODO: Check for more cases, such as new ArrayIterator(Object) and (new stdClass).
		if ($values instanceof \Iterator){
			return $values;
		} elseif (\is_array($values) === true){
			return new \ArrayIterator($values);
		} elseif ($values instanceof \IteratorAggregate){
			return $values->getIterator();
		} elseif ($values instanceof \Traversable){
			if ($values instanceof \DOMNodeList){
				//PHP 5.4.3 IteratorIterator does not behave itself with \DOMNodeList
	        	//https://bugs.php.net/bug.php?id=60762
	        	//As a workaround, eagerly copy the items to cache
		        return self::toList($values);
			} else {
				return new \IteratorIterator($values);
			}
		} elseif ($values instanceof \Closure){
			return Iterators::ensureIsIterator($values());
		} else {
			return new \EmptyIterator();
		}
	}

	/**
	 * Gets the first value, or null if there are no values
	 * @param unknown_type $values Any foreach-able object or value
	 * @param callable $selector Params ($value, $key). Returns true iff the item is selected.
	 * @return unknown_type|NULL The value, or null if there is no first value
	 */
	public static function firstOrNull($values, callable $selector = null){
		if ($selector === null){
			foreach($values as $value){
				return $value;
			}
			return null;
		} else {
			foreach($values as $key => $value){
				if ($selector($value, $key) === true){
					return $value;
				}
			}
			return null;
		}
	}

	/**
	 * Gets the first value, or throws IteratorException('Collection is empty')
	 * @param unknown_type $values Any foreach-able object or value
	 * @param callable $selector Params ($value, $key). Returns true iff the item is selected.
	 * @return unknown_type|NULL The value, or null if there is no first value
	 */
	public static function first($values, callable $selector = null){
		if ($selector === null){
			foreach($values as $value){
				return $value;
			}
		} else {
			foreach($values as $key => $value){
				if ($selector($value, $key) === true){
					return $value;
				}
			}
		}
		throw new IteratorException('Collection is empty');
	}

	/**
	 * Similar to \iterator_apply and \array_walk,
	 * except the parameters passed to the function
	 * follow the pattern used for \CallbackFilterIterator:
	 * $value, $key, $iterator.
	 */
	public static function forAll($values, callable $f){
		foreach($values as $key => $value){
			$f($value, $key, $values);
		}
	}

	/**
	 * \join(), but for any foreach-able object or value.
	 * See http://php.net/manual/en/function.join.php
	 * @param unknown_type $values Any foreach-able object or value
	 * @param string $separator
	 */
	public static function join($values, $separator){
		if (\is_array($values) === true){
			return \join($separator, $values);
		}
		$first = true;
		$retval = '';
		foreach($values as $value){
			if ($first === true){
				$retval = "$value";
				$first = false;
				continue;
			}
			$retval = "$retval$separator$value";
		}
		return $retval;
	}

	/**
	 * Convert $values to an array
	 * @param unknown_type $values
	 * @param bool $useKeys
	 */
	public static function toArray($values, $useKeys = false){
		if (\is_array($values) === true){
			if ($useKeys === true){
				return $values;
			} else {
				return \array_values($values);
			}
		} elseif ($values instanceof \Iterator){
			return \iterator_to_array($values, $useKeys);
		} elseif ($values instanceof \IteratorAggregate){
			$values = $values->getIterator();
			return \iterator_to_array($values, $useKeys);
		} elseif ($useKeys === true){
			$retval = [];
			foreach($values as $key => $value){
				$retval[$key] = $value;
			}
			return $retval;
		} else {
			$retval = [];
			foreach($values as $value){
				$retval[] = $value;
			}
			return $retval;
		}
	}

	/**
	 * Convert $values to an \SplFixedArray
	 * @param unknown_type $values
	 * @param bool $useKeys
	 */
	public static function toFixedArray($values, $useKeys = false){
		if (\is_array($values) === true){
			return \SplFixedArray::fromArray($values, $useKeys);
		} elseif ($values instanceof \Countable){
			$fixedArray = new \SplFixedArray($values->count());
			if ($useKeys === true){
				foreach($values as $key => $value){
					$fixedArray[$key] = $value;
				}
			} else {
				$i = 0;
				foreach($values as $value){
					$fixedArray[$i++] = $value;
				}
			}
			return $fixedArray;
		} else {
			$a = [];
			if ($useKeys === true){
				foreach($values as $key => $value){
					$a[$key] = $value;
				}
			} else {
				foreach($values as $value){
					$a[] = $value;
				}
			}
			return \SplFixedArray::fromArray($a, $useKeys);
		}
	}

	/**
	 * Copy $values to an \SplDoublyLinkedList.
	 * @param unknown_type $values Any foreachable object or value
	 */
	public static function toList($values){
		$list = new \SplDoublyLinkedList();
		foreach($values as $value){
			$list->push($value);
		}
		return $list;
	}

	/**
	 * Copy $values to an \SplObjectStroage.
	 * @param unknown_type $values Any foreachable object or value
	 * @param bool $keysAsData Attach keys to storage as data
	 */
	public static function toObjectStorage($values, $keysAsData = false){
		$o = new \SplObjectStorage();
		if ($keysAsData === true){
			foreach($values as $key => $item){
				$o->attach($item, $key);
			}
		} else {
			foreach($values as $item){
				$o->attach($item);
			}
		}
		return $o;
	}

	/**
	 * Iterates over all traits declared by a class or trait,
	 * including traits defined by declared traits and parent classes.
	 * @param unknown_type $classOrTrait
	 * @return \Iterator|\IteratorAggregate
	 */
	public static function traitsOf($classOrTrait){
		$traitsOf = static function($trait) use (&$traitsOf){
			$uses = \class_uses($trait);
			return (new \Yasca\Core\IteratorBuilder)
			->from($uses)
			->concat(
				(new \Yasca\Core\IteratorBuilder)
				->from($uses)
				->selectMany($traitsOf)
			);
		};

		return (new \Yasca\Core\IteratorBuilder)
		->from([$classOrTrait])
		->concat(\class_parents($classOrTrait))
		->selectMany($traitsOf);
	}
}