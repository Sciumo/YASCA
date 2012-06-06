<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * If the inner iterator has no items, then this iterator will have
 * exactly one item: $defaultValue at $defaultKey
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class DefaultIterator implements \Iterator {
	/** @var \Iterator */ private $innerIterator;
	private $defaultValue;
	private $defaultKey;
	private $innerHasItems = false;
	private $isFirstValidCall = true;

	public function __construct(\Iterator $iter, $defaultValue, $defaultKey = 0){
		if ($iter instanceof DefaultIterator){
			list($this->innerIterator, $this->defaultValue, $this->defaultKey) =
				\Closure::bind(
					function(){return [$this->innerIterator, $this->defaultValue, $this->defaultKey,];},
					$iter,
					$iter
				)->__invoke();
		} else {
			$this->innerIterator = $iter;
			$this->defaultValue = $defaultValue;
			$this->defaultKey = $defaultKey;
		}
	}

	public function current(){
		if ($this->innerHasItems){
			return $this->innerIterator->current();
		} else {
			return $this->defaultValue;
		}
	}
	public function key(){
		if ($this->innerHasItems){
			return $this->innerIterator->key();
		} else {
			return $this->defaultKey;
		}
	}
	public function next(){
		if ($this->innerHasItems){
			$this->innerIterator->next();
		}
	}
	public function rewind(){
		$this->isFirstValidCall = true;
		$this->innerIterator->rewind();
	}
	public function valid(){
		if ($this->isFirstValidCall){
			$this->innerHasItems = $this->innerIterator->valid();
			$this->isFirstValidCall = false;
			return true;
		}
		return $this->innerIterator->valid();
	}
}