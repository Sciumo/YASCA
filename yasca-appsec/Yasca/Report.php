<?
declare(encoding='UTF-8');
namespace Yasca;

/**
 * Report Class
 *
 * This (abstract) class is the parent of the specific report renderers. It handles
 * the output stream creation, sorting, and other housekeeping details.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 * @version 3
 * @license see doc/LICENSE
 * @package Yasca
 */
abstract class Report implements \SplObserver {
	public static function getInstalledReports() {
		return (new \Yasca\Core\IteratorBuilder)
		->from(new \FilesystemIterator(__DIR__ . '/Reports'))
		->whereRegex('`Report\.php$`ui', \RegexIterator::MATCH, \RegexIterator::USE_KEY)
		->select(static function($fileinfo){
			return $fileinfo->getBasename('.php');
		});
	}

	abstract public function update(\SplSubject $subject);
}