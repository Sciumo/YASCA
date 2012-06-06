<?
declare(encoding='UTF-8');
namespace Yasca\Reports;
use \Yasca\Core\Iterators;
use \Yasca\Core\JSON;
use \Yasca\Core\Closeable;

final class JsonStderrReport extends \Yasca\Report {
	use Closeable;

	const OPTIONS = <<<'EOT'
--report,JsonStderrReport[,jsonEncodingFlags]
jsonEncodingFlags: The numerical value of the json encoding flags from
	http://php.net/manual/en/function.json-encode.php
EOT;

	private $flags;
	private $firstResult = true;

	protected function innerClose(){
		\fwrite(STDERR, ']');
	}

	public function __construct($args){
		$flags = Iterators::elementAtOrNull($args, 0) ?: JSON_UNESCAPED_UNICODE;

		\fwrite(STDERR, '[');
		$this->flags = $flags;
	}

	public function update(\SplSubject $subject){
		$result = $subject->value;
		if ($this->firstResult === true){
			$this->firstResult = false;
		} else {
			\fwrite(STDERR,',');
		}
		\fwrite(STDERR, JSON::encode($result, $this->flags));
	}
}