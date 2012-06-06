<?
declare(encoding='UTF-8');
namespace Yasca\Core;

/**
 * Simulates an event with a value.
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class SplSubjectAdapter implements \SplSubject {

	/** @var \SplObjectStorage */ private $observers;
	public function __construct(){
		$this->observers = new \SplObjectStorage();
	}
	public function attach(\SplObserver $observer){
		$this->observers->attach($observer);
	}
	public function detach(\SplObserver $observer){
		$this->observers->detach($observer);
	}
	public function notify(){
		foreach($this->observers as $observer){
			$observer->update($this);
		}
	}
	public function raise($value){
		$this->value = $value;
		$this->notify();
		unset($this->value);
	}
}