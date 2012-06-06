<?
declare(encoding='UTF-8');
namespace Yasca\Plugins\FindBugs;
use \Yasca\Core\Async;
use \Yasca\Core\Environment;
use \Yasca\Core\Process;
use \Yasca\Core\ProcessStartException;

final class Plugin extends \Yasca\Plugin {
	use \Yasca\MulticastPlugin;

	protected function getSupportedFileClasses(){return ['class', 'jar',];}

    public function getResultIterator($path){
    	if (Environment::hasAtLeastJavaVersion(5) !== true){
    		$this->log(['FindBugs requires JRE 1.5 or later.', \Yasca\Logs\Level::ERROR]);
    		return new \EmptyIterator();
    	}
    	try {
    		$process = new Process(
    			'"' . __DIR__ . '/bin/findbugs' .
		    	(Environment::isWindows() ? '.bat' : '') . '"' .
		    	' -home "' . __DIR__ . '" ' .
		    	' -include "' . __DIR__ . '/filter.xml" ' .
		    	'-textui -xml:withMessages -xargs -quiet'
			);
    	} catch (ProcessStartException $e){
    		$this->log(['FindBugs failed to start', \Yasca\Logs\Level::ERROR]);
	    	return new \EmptyIterator();
    	}
	    $this->log(['FindBugs launched', \Yasca\Logs\Level::INFO]);

        (new \Yasca\Core\IteratorBuilder)
        ->from(new \RecursiveDirectoryIterator($path))
        ->where(function($fileinfo){
        	return $this->supportsExtension($fileinfo->getExtension());
		})
		->select(static function($fileinfo, $filepath){
			return "$filepath\n";
		})
		->forAll([$process,'writeToStdin']);

        $process->closeStdin();

        return $process->whenCompleted(function($stdout) use ($path){
        	$this->log(['FindBugs completed', \Yasca\Logs\Level::INFO]);
        	if ($stdout === ''){
        		return new \EmptyIterator();
        	}
        	$dom = new \DOMDocument();
        	try {
        		$success = $dom->loadXML($stdout);
        	} catch (\ErrorException $e){
        		$success = false;
        	}
        	if ($success !== true){
        		$this->log(['FindBugs did not return valid XML', \Yasca\Logs\Level::ERROR]);
        		$this->log(["FindBugs returned $stdout", \Yasca\Logs\Level::INFO]);
		        return new \EmptyIterator();
        	}

        	$bugPatterns =
        		(new \Yasca\Core\IteratorBuilder)
        		->from($dom->getElementsByTagName('BugPattern'))
        		->selectKeys(static function($patternNode){
        			return [
	        			"{$pattern->getElementsByTagName('Details')->item(0)->nodeValue}",
	        			"{$pattern->getAttribute('type')}"
	        		];
	        	})
	        	->toArray(true);

	        return (new \Yasca\Core\IteratorBuilder)
	        ->from($dom->getElementsByTagName('BugInstance'))
	        ->select(static function($bugInstance) use (&$bugPatterns, $path){
            	$type = $bugInstance->getAttribute('type');
            	$sourceLine = $bugInstance->getElementsByTagName('SourceLine')->item(0);
            	$shortMessage = $bugInstance->getElementsByTagName('ShortMessage')->item(0)->nodeValue;
	        	return (new \Yasca\Result)->setOptions([
					'pluginName' => 'FindBugs',
	        		'severity' => "{$bugInstance->getAttribute('priority')}",
	        		'category' =>
	        			\ucwords(\strtolower(\str_replace('_', ' ', $bugInstance->getAttribute('category')))),
	        		'lineNumber' => "{$sourceLine->getAttribute('start')}",
	        		'filename' => "$path/{$sourceLine->getAttribute('sourcepath')}",
	        		'references' => [
	        			'http://findbugs.sourceforge.net/bugDescriptions.html#' . \urlencode($type) =>
	        				'FindBugs Bug Description',
	        		],
	        		'message' => "$shortMessage",
	        		'description' => <<<"EOT"
$shortMessage

$bugPatterns[$type]
EOT
,
	        	]);
	        });
        });
    }
}