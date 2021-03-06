<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Projects the values from an iterator using a callable.
 * A lazy evaluated version of \array_map
 * See http://php.net/manual/en/function.array-map.php
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class ProjectionIterator implements \Iterator {
	/** @var \Iterator */ private $innerIterator;
	private $current;
	/** @var callable */ private $projection;
	/** @var bool */ private $projectionNeeded = true;

	/**
	 * Project the given iterator to new values.
	 * @param \Iterator $iter
	 * @param callable $projection Params: (value, key, iterator). Returns new value.
	 */
	public function __construct(\Iterator $iter, callable $projection){
		//When stacking, curry the projection instead.
		if ($iter instanceof ProjectionIterator){
			list($innerProjection, $this->innerIterator) =
				\Closure::bind(
					function(){return [$this->projection, $this->innerIterator,];},
					$iter,
					$iter
				)->__invoke();
			$this->projection = static function($current, $key, $iterator) use ($projection, $innerProjection){
				return $projection($innerProjection($current, $key, $iterator), $key, $iterator);
			};
		} else {
			$this->innerIterator = $iter;
			$this->projection = $projection;
		}
	}

	public function current(){
		if ($this->projectionNeeded){
			$projection = $this->projection;
			$this->current = $projection(
				$this->innerIterator->current(),
				$this->innerIterator->key(),
				$this->innerIterator);
			$this->projectionNeeded = false;
		}
		return $this->current;
	}
	public function key(){return $this->innerIterator->key();}
	public function next(){
		$this->projectionNeeded = true;
		unset($this->current);
		return $this->innerIterator->next();
	}
	public function rewind(){
		$this->projectionNeeded = true;
		unset($this->current);
		$this->innerIterator->rewind();
	}
	public function valid(){return $this->innerIterator->valid();
	}
}