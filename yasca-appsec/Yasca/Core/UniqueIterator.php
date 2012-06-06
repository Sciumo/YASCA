<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class UniqueIterator implements \Iterator {
	/** @var \Iterator */ private $innerIterator;
	private $array;
	private $objectStorage;

	/**
	 * @param \Iterator $iter
	 */
	public function __construct(\Iterator $iter){
		if ($iter instanceof UniqueIterator){
			$this->innerIterator = $iter->innerIterator;
		} else {
			$this->innerIterator = $iter;
		}
		$this->array = [];
		$this->objectStorage = new \SplObjectStorage();
	}

	public function current(){return $this->innerIterator->current();}
	public function key(){return $this->innerIterator->key();}
	public function next(){
		$current = $this->current();
		if (\is_scalar($current)){
			$this->array[$current] = true;
		} else {
			$this->objectStorage->attach($current);
		}
		$this->innerIterator->next();
	}
	public function rewind(){
		$this->innerIterator->rewind();
		$this->array = [];
		$this->objectStorage = new \SplObjectStorage();
	}
	public function valid(){
		while($this->innerIterator->valid() === true){
			$current = $this->current();
			if (\is_scalar($current)){
				if (isset($this->array[$current]) !== true){
					return true;
				}
			} else {
				if ($this->objectStorage->contains($current) !== true){
					return true;
				}
			}
			$this->innerIterator->next();
		}
		return false;
	}
}