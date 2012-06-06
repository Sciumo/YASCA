<?
declare(encoding='UTF-8');
namespace Yasca\Reports;
use \Yasca\Core\Iterators;
use \Yasca\Core\JSON;
use \Yasca\Core\Closeable;

final class HtmlFileReport extends \Yasca\Report {
	use Closeable;

	const OPTIONS = <<<'EOT'
--report,HtmlFileReport[,filename]
filename: The name of the file to write, relative to the current working directory
EOT;

	private $fileObject;
	private $firstResult = true;

	public function __construct($args){
		$filename = Iterators::elementAtOrNull($args, 0);

		$this->fileObject = new \SplFileObject($filename, 'w');

		$c = static function(callable $c){return $c();};
		$this->fileObject->fwrite(<<<"EOT"
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<title>Yasca v3 - Report</title>
<style type="text/css">
{$c(static function(){return \file_get_contents(__DIR__ . '/HtmlFileReport.css');})}
</style>
<script type="text/javascript">
{$c(static function(){return \file_get_contents(__DIR__ . '/jquery-1.7.1.min.js');})}
</script>
<script type="text/javascript">
{$c(static function(){return \file_get_contents(__DIR__ . '/HtmlFileReport.js');})}
</script>
</head>
<body>
<table class="header" cellspacing="0" cellpadding="0">
	<tr>
		<td class="title">Yasca</td>
        <td style="width: 100%;">
        	<table style="border:0;">
            <tr><td class="header_left">Yasca Version:</td><td class="header_right">3 [ <a target="_blank" href="http://sourceforge.net/projects/yasca/files/">check for updates</a> ]</td></tr>
            <tr><td class="header_left">Report Generated:</td><td class="header_right">
{$c(static function(){return \htmlspecialchars(\date(\DateTime::RFC850),ENT_NOQUOTES);})}
            </td></tr>
            </table>
    	</td>
    </tr>
</table>

<h1 id="loading">Loading...</h1>
<script type="text/javascript">
"use strict";
Yasca.results = [
EOT
	);
}
	public function update(\SplSubject $subject){
		$result = $subject->value;
		if ($this->firstResult === true){
			$this->firstResult = false;
		} else {
			$this->fileObject->fwrite(',');
		}
		$this->fileObject->fwrite(JSON::encode($result, JSON_UNESCAPED_UNICODE));
	}

	protected function innerClose(){
		$this->fileObject->fwrite(<<<'EOT'
];
</script>
</body>
</html>
EOT
		);
		unset($this->fileObject);
	}
}