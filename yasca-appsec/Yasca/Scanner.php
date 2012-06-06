<?
declare(encoding='UTF-8');
namespace Yasca;
use \Yasca\Core\Async;
use \Yasca\Core\CallablePropertiesAsMethods;
use \Yasca\Core\Encoding;
use \Yasca\Core\Iterators;
use \Yasca\Core\JSON;
use \Yasca\Core\SplSubjectAdapter;

/**
 *
 * This is the main engine behind Yasca. It handles passed options, scanning for target
 * files and plugins, and executing those plugins. The output of this all is a list of
 * Result objects that can be passed to a renderer.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 * @version 3
 * @license see doc/LICENSE
 * @package Yasca
 */
final class Scanner {
	use CallablePropertiesAsMethods;

	const SECONDS_PER_NOTIFY = 30;

	private static $adjustments;

	public function __construct($options){
		//PHP 5.4.3 does not have a way to immediately invoke anonymous functions
		$c = static function(callable $c){return $c();};

		$closeables = new \SplObjectStorage();
		$subscribeIfCloseable = static function($object) use ($closeables){
			if (
				(new \Yasca\Core\IteratorBuilder)
				->from(Iterators::traitsOf($object))
				->contains('Yasca\Core\Closeable')
			){
				$closeables->attach($object);
			}
		};

		$newEvent = function($name) use ($subscribeIfCloseable){
			$event = new SplSubjectAdapter();
			$this->{"attach{$name}Observer"} = function(\SplObserver $observer) use ($event, $subscribeIfCloseable){
				$event->attach($observer);
				$subscribeIfCloseable($observer);
				return $this;
			};
			$this->{"detach{$name}Observer"} = function(\SplObserver $observer) use ($event, $subscribeIfCloseable){
				$event->detach($observer);
				$subscribeIfCloseable($observer);
				return $this;
			};
			return static function($value) use ($event){
				$event->raise($value);
			};
		};

		$fireLogEvent = $newEvent('Log');

		$targetDirectory = \realpath(Iterators::elementAtOrNull($options, 'targetDirectory'));

		$makeRelative = $c(static function() use ($targetDirectory){
			//Make filenames relative when publishing a result
			$dirLiteral = \preg_quote($targetDirectory, '`');
			$regex = "`^$dirLiteral`ui";
			return static function($filename) use ($regex){
				return \preg_replace($regex, '', $filename);
			};
		});

		$fireResultEvent = $newEvent('Result');
		$fireResultEvent = static function(Result $result) use ($fireResultEvent, $makeRelative){
			//Make adjustments based on adjustments data
			$categories = Iterators::elementAtOrNull(static::$adjustments, $result->pluginName);
			$changes = Iterators::elementAtOrNull($categories, $result->category);
			if ($changes !== null){
				$result->setOptions($changes);
			}

			//Get unsafeSourceCode if needed, and then make the filename relative
			//to the scan directory
			if (isset($result->filename) === true){
				if(isset($result->lineNumber) === true && isset($result->unsafeSourceCode) !== true){
					try {
						$result->unsafeSourceCode =
							(new \Yasca\Core\IteratorBuilder)
							->from(Encoding::getFileContentsAsArray($result->filename))
							->slice(
								\max($result->lineNumber - 10, 0),
								20
							)
							->toArray(true);
					} catch (\ErrorException $e){
						$tail = 'No such file or directory';
						if (\substr($e->getMessage(),0-strlen($tail)) === $tail){
							//External tool generated a filename that's not present
							//FindBugs can often do this if the matching .java files are missing.
						} else {
							throw $e;
						}
					}
				}
				$result->setOptions([
					'filename' => "{$makeRelative($result->filename)}",
				]);
			}
			$fireResultEvent($result);
		};

		$createPlugins = $c(static function() use ($c, &$options, $fireLogEvent){
			$ignoreRegex = $c(static function() use (&$options){
				$start = '`^(?!.*(';
				$finish = ').*$)`u';
				$retval =
					$start .
					(new \Yasca\Core\IteratorBuilder)
					->from(Iterators::elementAtOrNull($options, 'pluginsIgnore'))
					->select(static function($literal){return \preg_quote($literal, '`');})
					->join('|') .
					$finish;
				if ($retval === $start . $finish){
					return null;
				}
				return $retval;
			});

			$onlyRegex = $c(static function() use (&$options){
				$start = '`(';
				$finish = ')`u';
				$retval =
					$start .
					(new \Yasca\Core\IteratorBuilder)
					->from(Iterators::elementAtOrNull($options, 'pluginsOnly'))
					->select(static function($literal){return \preg_quote($literal, '`');})
					->join('|') .
					$finish;
				if ($retval === $start . $finish){
					return null;
				}
				return $retval;
			});
			return static function() use ($ignoreRegex, $onlyRegex, $fireLogEvent){
				$retval =
					(new \Yasca\Core\IteratorBuilder)
					->from(Plugin::$installedPlugins)
					->select(static function($plugins) use ($ignoreRegex, $onlyRegex, $fireLogEvent){
						return (new \Yasca\Core\IteratorBuilder)
						->from($plugins)
						->whereRegex($ignoreRegex)
						->whereRegex($onlyRegex)
						->select(static function($pluginName) use ($fireLogEvent){
							$p = new $pluginName($fireLogEvent);
							$fireLogEvent(["Plugin $pluginName Loaded", \Yasca\Logs\Level::DEBUG]);
							return $p;
						})
						->toObjectStorage();
					})
					->where(static function($plugins){return Iterators::any($plugins);})
					->toArray(true);
				$fireLogEvent(['Selected Plugins Loaded', \Yasca\Logs\Level::DEBUG]);
				return $retval;
			};
		});

		$createTargetIterator = $c(static function() use ($c, &$options, $targetDirectory){
			$extensionsIgnoreRegex = $c(static function() use (&$options){
				$start = '`(?<!';
				$finish = ')$`ui';
				$retval =
					$start .
					(new \Yasca\Core\IteratorBuilder)
					->from(Iterators::elementAtOrNull($options, 'extensionsIgnore'))
					->select(static function($ext){return '.' . \trim($ext, '.');})
					->select(static function($literal){return \preg_quote($literal, '`');})
					->join('|') .
					$finish;
				if ($retval === $start . $finish){
					return null;
				}
				return $retval;
			});
			$extensionsOnlyRegex = $c(static function() use (&$options){
				$start = '`(';
				$finish = ')$`ui';
				$retval =
					$start .
					(new \Yasca\Core\IteratorBuilder)
					->from(Iterators::elementAtOrNull($options, 'extensionsOnly'))
					->select(static function($ext){return '.' . \trim($ext, '.');})
					->select(static function($literal){return \preg_quote($literal, '`');})
					->join('|') .
					$finish;
				if ($retval === $start . $finish){
					return null;
				}
				return $retval;
			});
			$extensionRegex = static function($pluginArray){
				return
				'`\.(' .
				(new \Yasca\Core\IteratorBuilder)
				->from($pluginArray)
				->from(static function($plugins){
					return (new \Yasca\Core\IteratorBuilder)
					->from($plugins);
				})
				->selectMany(static function($plugin){
					return (new \Yasca\Core\IteratorBuilder)
					->from($plugin->getSupportedFileTypes());
				})
				->unique()
				->select(static function($ext){
					return \preg_quote($ext, '`');
				})
				->join('|') .
				')$`ui';
			};
			return static function($pluginArray) use ($targetDirectory, $extensionRegex, $extensionsIgnoreRegex, $extensionsOnlyRegex){
				//Only select files that plugins ask for
				return (new \Yasca\Core\IteratorBuilder)
				->from(new \RecursiveDirectoryIterator($targetDirectory,
					\FilesystemIterator::KEY_AS_PATHNAME 	 |
					\FilesystemIterator::CURRENT_AS_FILEINFO |
					\FilesystemIterator::UNIX_PATHS
				))
				->whereRegex($extensionRegex($pluginArray), \RegexIterator::MATCH, \RegexIterator::USE_KEY)
				->whereRegex($extensionsIgnoreRegex, \RegexIterator::MATCH, \RegexIterator::USE_KEY)
				->whereRegex($extensionsOnlyRegex, \RegexIterator::MATCH, \RegexIterator::USE_KEY)
				;
			};
		});

		$triggerScanComplete = static function() use (
			$fireLogEvent, $fireResultEvent,
			$closeables
		){
			$fireLogEvent(['Scan complete', \Yasca\Logs\Level::INFO]);
			foreach($closeables as $closeable){
				$closeable->close();
			}
		};


		$executeAsync = static function() use (
			$fireLogEvent, $fireResultEvent,
			$triggerScanComplete,
			$makeRelative, $createPlugins,
			$targetDirectory, $createTargetIterator
		){
			$fireLogEvent(['Yasca 3 - http://www.yasca.org/ - Michael V. Scovetta', \Yasca\Logs\Level::INFO]);
			$fireLogEvent(["Scanning $targetDirectory", \Yasca\Logs\Level::INFO]);


			$awaits = new \SplQueue();

			$processResults = static function($results) use ($fireResultEvent, &$awaits, &$processResults){
				if 		 ($results instanceof Async){
					if ($results->isDone() === true){
						$processResults($results->getResult());
					} else {
						$awaits->enqueue($results);
					}
				} elseif ($results instanceof Result){
					$fireResultEvent($results);
				} else {
					foreach($results as $result){
						$fireResultEvent($result);
					}
				}
			};

			$plugins = $createPlugins();

			$multicasts = Iterators::elementAtOrNull($plugins, __NAMESPACE__ . '\MulticastPlugin');

			$lastStatusReportedTime = \time();
			$filesProcessed = 0;
			foreach($createTargetIterator($plugins) as $filePath => $targetFileInfo){
				Async::tick();
				$oldAwaits = $awaits;
				$awaits = new \SplQueue();
				foreach($oldAwaits as $await){
					$processResults($await);
				}

				$fireLogEvent(["Checking file {$makeRelative($filePath)}", \Yasca\Logs\Level::DEBUG]);

				$n = \time();
				if ($n - $lastStatusReportedTime > self::SECONDS_PER_NOTIFY){
					$fireLogEvent(["$filesProcessed files scanned", \Yasca\Logs\Level::INFO]);
					$lastStatusReportedTime = $n;
				}

				$ext = $targetFileInfo->getExtension();

				(new \Yasca\Core\IteratorBuilder)
				//Make a copy to allow removing elements iterated over
				->from(Iterators::toList(Iterators::ensureIsIterator($multicasts)))
				->where(static function($plugin) use ($ext){
					return $plugin->supportsExtension($ext);
				})
				->select(static function($plugin) use ($multicasts, $targetDirectory){
					$multicasts->detach($plugin);
					return $plugin->getResultIterator($targetDirectory);
				})
				->forAll($processResults);

				(new \Yasca\Core\IteratorBuilder)
				->from(Iterators::elementAtOrNull($plugins, __NAMESPACE__ . '\SingleFilePathPlugin'))
				->where(static function($plugin) use ($ext){
					return $plugin->supportsExtension($ext);
				})
				->select(static function($plugin) use ($filePath){
					return $plugin->getResultIterator($filePath);
				})
				->forAll($processResults);

				$getFileContents = static function() use ($filePath){
					static $retval = null;
					if (isset($retval) !== true){
						$retval = Encoding::getFileContentsAsArray($filePath);
					}
					return $retval;
				};

				(new \Yasca\Core\IteratorBuilder)
				->from(Iterators::elementAtOrNull($plugins, __NAMESPACE__ . '\SingleFileContentsPlugin'))
				->where(static function($plugin) use ($ext){
					return $plugin->supportsExtension($ext);
				})
				->select(static function($plugin) use ($getFileContents, $filePath){
					return $plugin->getResultIterator($getFileContents(), $filePath);
				})
				->forAll($processResults);

				(new \Yasca\Core\IteratorBuilder)
				->from(Iterators::elementAtOrNull($plugins, __NAMESPACE__ . '\AggregateFileContentsPlugin'))
				->where(static function($plugin) use ($ext){
					return $plugin->supportsExtension($ext);
				})
				->forAll(static function($plugin) use ($getFileContents, $filePath){
					$plugin->apply($getFileContents(), $filePath);
				});

				$filesProcessed++;
			}
			$fireLogEvent(['Finished with files. Gathering results from Aggregate plugins', \Yasca\Logs\Level::DEBUG]);

			(new \Yasca\Core\IteratorBuilder)
			->from(Iterators::elementAtOrNull($plugins, __NAMESPACE__ . '\AggregateFileContentsPlugin'))
			->select(static function($plugin){return $plugin->getResultIterator();})
			->forAll($processResults);

			if (Iterators::any($awaits) === true){
				$fireLogEvent(['Waiting on external plugins', \Yasca\Logs\Level::INFO]);
			}
			return new Async(
				static function() use (&$awaits, $processResults){
					$oldAwaits = $awaits;
					$awaits = new \SplQueue();
					foreach($oldAwaits as $await){
						$processResults($await);
					}
					return Iterators::any($awaits) !== true;
				},
				$triggerScanComplete
			);
		};

		if (Iterators::elementAtOrNull($options, 'debug') !== true){
			$executeAsync = static function() use ($executeAsync, $fireLogEvent, $triggerScanComplete){
				try {
					$async = $executeAsync();
				} catch (\Exception $e){
					$fireLogEvent([$e->getMessage(), \Yasca\Logs\Level::ERROR]);
					$triggerScanComplete();
					return new Async(static function(){return true;},static function(){});
				}
				return new Async(
					static function() use ($async){return $async->isDone();},
					static function() use ($async, $fireLogEvent, $triggerScanComplete){
						try {
							$async->getResult();
						} catch (\Exception $e){
							$fireLogEvent([$e->getMessage(), \Yasca\Logs\Level::ERROR]);
							$triggerScanComplete();
						}
					}
				);
			};
		}

		$this->executeAsync = $executeAsync;
		$this->execute = static function() use ($executeAsync){
			$executeAsync()->getResult();
		};
	}
}
\Closure::bind(
	static function(){
		static::$adjustments = JSON::decode(
			\file_get_contents(__FILE__ . '.adjustments.json'),
			true
		);
	},
	null,
	__NAMESPACE__ . '\\' . \basename(__FILE__, '.php')
)->__invoke();