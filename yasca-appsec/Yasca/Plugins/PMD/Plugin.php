<?
declare(encoding='UTF-8');
namespace Yasca\Plugins\PMD;
use \Yasca\Core\Async;
use \Yasca\Core\Environment;
use \Yasca\Core\Iterators;
use \Yasca\Core\Process;
use \Yasca\Core\ProcessStartException;

/**
 * The PMD Plugin uses PMD to discover potential vulnerabilities in .java files.
 * This class is a Singleton that runs only once, returning all of the results that
 * first time.
 * @extends Plugin
 * @package Yasca
 */
final class Plugin extends \Yasca\Plugin {
	use \Yasca\MulticastPlugin;

    protected function getSupportedFileClasses(){return ['JAVA', 'jsp', ];}

    public function getResultIterator($path){
    	if (Environment::hasAtLeastJavaVersion(4) !== true){
    		$this->log(['PMD requires JRE 1.4 or later.', \Yasca\Logs\Level::ERROR]);
    		return new \EmptyIterator();
    	}

        try {
    		$process = new Process(
    			'java -cp "' .
    			(new \Yasca\Core\IteratorBuilder)
    			->from(new \FilesystemIterator(__DIR__))
    			->select(static function($u, $key){return $key;})
    			->whereRegex('`\.jar$`ui')
    			->join(';') .
    			'" net.sourceforge.pmd.PMD "' . $path . '"' .
    			' net.sourceforge.pmd.renderers.YascaRenderer' .
    			' "' . __DIR__ . '/yasca-rules.xml"'
			);
    	} catch (ProcessStartException $e){
    		$this->log(['PMD failed to start', \Yasca\Logs\Level::ERROR]);
	    	return new \EmptyIterator();
    	}
	    $this->log(['PMD launched', \Yasca\Logs\Level::INFO]);

	    return $process->whenCompleted(function($stdout){
        	$this->log(['PMD completed', \Yasca\Logs\Level::INFO]);
	        $dom = new \DOMDocument();
	        try {
	        	$success = $dom->loadXML($stdout);
	        } catch (\ErrorException $e){
	        	$success = false;
	        }
	        if ($success !== true){
	        	$this->log(['PMD did not return valid XML', \Yasca\Logs\Level::INFO]);
	        	$this->log(["PMD returned $stdout", \Yasca\Logs\Level::DEBUG]);
			    return new \EmptyIterator();
	        }

	        return (new \Yasca\Core\IteratorBuilder)
	        ->from($dom->getElementsByTagName('file'))
	        ->selectMany(static function($fileNode){
	        	return (new \Yasca\Core\IteratorBuilder)
	        	->from($fileNode->getElementsByTagName('violation'))
	        	->select(static function($violationNode) use ($fileNode){
	        		return (new \Yasca\Result)->setOptions([
        				'pluginName' => 'PMD',
        				'filename' => "{$fileNode->getAttribute('name')}",
        				'lineNumber' => "{$violationNode->getAttribute('beginline')}",
        				'category' => "{$violationNode->getAttribute('rule')}",
        				'severity' => "{$violationNode->getAttribute('priority')}",
        				'description' => Iterators::firstOrNull($violationNode->getElementsByTagName('description'))->nodeValue,
        				'message' => Iterators::firstOrNull($violationNode->getElementsByTagName('message'))->nodeValue,
        				'references' => [
        					"{$violationNode->getAttribute('externalInfoUrl')}" => 'PMD Reference',
        				],
        			]);
	        	});
	        });
        });
    }
}