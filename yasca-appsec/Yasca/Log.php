<?
declare(encoding='UTF-8');
namespace Yasca;

abstract class Log implements \SplObserver {
	public static function getInstalledLogs(){
		return (new \Yasca\Core\IteratorBuilder)
		->from(new \FilesystemIterator(__DIR__ . '/Logs'))
		->whereRegex('`Log\.php$`ui', \RegexIterator::MATCH, \RegexIterator::USE_KEY)
		->select(static function($fileinfo){
			return $fileinfo->getBasename('.php');
		});
	}

	abstract public function update(\SplSubject $subject);
}