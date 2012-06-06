<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Allows composing PHP collections and projections in a functional style
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class IteratorBuilder implements \IteratorAggregate {
	public function __call($name, array $arguments){
		if (isset($this->$name) === true){
			$f = $this->$name;
			$argCount = \count($arguments);
		} else {
			//Forward calls not defined locally to Iterators
			$f = [__NAMESPACE__ . '\Iterators', $name];
			$argCount = \array_unshift($arguments, $this->iterator);
		}

		//\call_user_func() is expensive. Avoid for most calls.
		//As of PHP 5.4.3, switch does not use strict equality
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

	private $iterator = null;
	public function getIterator(){
		return $this->iterator;
	}

	public function __construct(){
		$this->from = function($values){
			$this->iterator = Iterators::ensureIsIterator($values);

			//TODO: More intelligent handling of RecursiveIterators
			if ($this->iterator instanceof \RecursiveIterator){
				$this->iterator = new \RecursiveIteratorIterator($this->iterator);
			}

			$this->where = function(callable $filter){
				$this->iterator = new \CallbackFilterIterator($this->iterator, $filter);
				return $this;
			};

			$this->whereRegex = function($regex, $mode = \RegexIterator::MATCH, $flags = 0, $preg_flags = 0){
				/**
				 * Ignores calls where $regex === null
				 */
				if ($regex !== null){
					$this->iterator = new \RegexIterator($this->iterator, $regex, $mode, $flags, $preg_flags);
				}
				return $this;
			};

			$this->select = function(callable $projection){
				$this->iterator = new ProjectionIterator($this->iterator, $projection);
				return $this;
			};

			$this->selectKeys = function(callable $projection){
				$this->iterator = new ProjectionKeyIterator($this->iterator, $projection);
				return $this;
			};

			$this->selectMany = function(callable $manyProjection){
				$this->iterator = new ManyProjectionIterator($this->iterator, $manyProjection);
				return $this;
			};

			//Overwrite old 'from'
			$this->from = $this->selectMany;

			$this->concat = function(/* $... */){
				//AppendIterator is explicitly avoided
				//https://bugs.php.net/bug.php?id=62212

				$args = \func_get_args();
				if (Iterators::any($args) === true){
					$iterators = new \SplDoublyLinkedList();
					$iterators->push($this->iterator);
					foreach($args as $value){
						$iter = Iterators::ensureIsIterator($value);
						$iterators->push($iter);
					}
					$this->iterator = new ManyProjectionIterator($iterators, static function($iter){
						return $iter;
					});
				}
				return $this;
			};

			$this->slice = function($offset, $count){
				$this->iterator = new \LimitIterator($this->iterator, $offset, $count);
				return $this;
			};

			$this->skip = function($offset){
				$this->iterator = new \LimitIterator($this->iterator, $offset);
				return $this;
			};

			$this->take = function($count){
				$this->iterator = new \LimitIterator($this->iterator, 0, $count);
				return $this;
			};

			$this->groupBy = function(callable $selector){
				/**
				 * Group the provided $values, using the result of $selector as the array key.
				 * @param unknown_type $values Any foreachable object or value
				 * @param callable $selector Params ($value, $key, $values). Returns any array key.
				 * @return array of \Iterator
				 */
				$grouping = [];
				foreach($this->iterator as $key => $value){
					$groupKey = $selector($value, $key, $this->iterator);
					if (isset($grouping[$groupKey]) !== true){
						$grouping[$groupKey] = new \SplQueue();
					}
					$grouping[$groupKey]->enqueue($value);
				}
				$this->iterator = Iterators::ensureIsIterator($grouping);
				return $this;
			};

			$this->defaultIfEmpty = function($value){
				$this->iterator = new DefaultIterator($this->iterator, $value);
				return $this;
			};

			$this->unique = function(){
				$this->iterator = new UniqueIterator($this->iterator);
				return $this;
			};
			return $this;
		};
	}
}