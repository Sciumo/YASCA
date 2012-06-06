<?
declare(encoding='UTF-8');
namespace Yasca\Logs;
use \Yasca\Core\Iterators;

/**
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class ConsoleLog extends \Yasca\Log {
	const OPTIONS = <<<'EOT'
--log,ConsoleLog[,filename,levels]
levels: The numerical value of the level flags (DEBUG: 1, INFO: 2, ERROR: 4)
EOT;
	private $levels;
	private $prefix;
	public function __construct($args = []){
		$this->levels = Iterators::elementAtOrNull($args, 0);
		if ($this->levels === null){
			$this->levels = (/*Level::DEBUG |*/ Level::INFO | Level::ERROR);
		}

		$this->prefix = Iterators::elementAtOrNull($args, 1);
		if ($this->prefix === null){
			$this->prefix = '';
		}
	}

	public function update(\SplSubject $subject){
		list($message, $severity) = $subject->value;
		if (($severity & $this->levels) !== $severity){
			return;
		} elseif ($severity === Level::DEBUG){
			print("{$this->prefix}DEBUG  $message\n");
		} elseif ($severity === Level::INFO){
			print("{$this->prefix}INFO   $message\n");
		} elseif ($severity === Level::ERROR){
			print("{$this->prefix}ERROR  $message\n");
		} else {
			//Ignore it.
		}
	}
}