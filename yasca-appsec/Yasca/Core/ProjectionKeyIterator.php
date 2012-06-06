<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Projects the values and keys from an iterator using a callable.
 * A lazy evaluated version of \array_map
 * See http://php.net/manual/en/function.array-map.php
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class ProjectionKeyIterator implements \Iterator {
	/** @var \Iterator */ private $innerIterator;
	/** @var bool */ private $projectionNeeded = true;
	/** @var callable */ private $projection;
	private $current;
	private $currentKey;

	/**
	 * @param \Iterator $iter
	 * @param callable $projection Params: (value, key, iterator). Returns [newValue, newKey].
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
		} elseif ($iter instanceof ProjectionKeyIterator){
			list($innerProjection, $this->innerIterator) =
				\Closure::bind(
					function(){return [$this->projection, $this->innerIterator,];},
					$iter,
					$iter
				)->__invoke();
			$this->projection = static function($current, $key, $iterator) use ($projection, $innerProjection){
				list($current, $key) = $innerProjection($current, $key, $iterator);
				return $projection($current, $key, $iterator);
			};
		} else {
			$this->innerIterator = $iter;
			$this->projection = $projection;
		}
	}

	private function project(){
		$projection = $this->projection;
		list($this->current, $this->currentKey) = $projection(
			$this->innerIterator->current(),
			$this->innerIterator->key(),
			$this->innerIterator);
		$this->projectionNeeded = false;
	}

	public function current(){
		if ($this->projectionNeeded){ $this->project();}
		return $this->current;
	}
	public function key(){
		if ($this->projectionNeeded){ $this->project();}
		return $this->currentKey;
	}
	public function next(){
		$this->projectionNeeded = true;
		unset($this->current);
		unset($this->currentKey);
		return $this->innerIterator->next();
	}
	public function rewind(){
		$this->projectionNeeded = true;
		unset($this->current);
		unset($this->currentKey);
		$this->innerIterator->rewind();
	}
	public function valid(){
		return $this->innerIterator->valid();
	}
}