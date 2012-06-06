<?
declare(encoding='UTF-8');

\set_error_handler(static function($errno, $errstr, $errfile, $errline){
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

\spl_autoload_extensions('.php');
\spl_autoload_register();

//Wrap the following code to not leave variables in local scope
\call_user_func(static function(){

/**
 * Yasca Engine, Yasca Static Analysis Tool
 *
 * This package implements a simple engine for static analysis
 * of source code files.
 * @author Michael V. Scovetta <scovetta@sourceforge.net>
 * @author Cory Carson <cory.carson@boeing.com>
 * @version 3
 * @license see doc/LICENSE
 * @package Yasca
 */

$scannerOptions = [];

$addDefaultLog = true;
$logs = new \SplQueue();

$addDefaultReport = true;
$reports = new \SplQueue();

$version = <<<'EOT'
Yasca version 3
Copyright (c) 2010 Michael V. Scovetta. See docs/LICENSE for license information.
EOT;

$help = <<<"EOT"
$version

Usage: [options] directory
Perform analysis of program source code.
  -h, --help                show this help
  -v, --version				show only the version info

  --extensionsOnly,EXT[,EXT]+    only scan these file extensions
  --extensionsIgnore,EXT[,EXT]+  ignore these file extensions

  --pluginsIgnore,NAMEPART[,NAMEPART]+  ignore plugins containing these
  --pluginsOnly,NAMEPART[,NAMEPART]+    only use plugins containing these
  --pluginsInstalled				    Do not perform scan. Print names of installed plugins

  -l,TYPE[,OPTIONS]+
  --log,TYPE[,OPTIONS]+
  		Uses the TYPE of log plugin, and provides OPTIONS to that type.
  		Multiple log switches can be used.
  		If no switch specified, behaves as --log:ConsoleLog,STDOUT
  --logOptions,TYPE						Do not perform scan. Print help for options of log TYPE
  --logInstalled						Do not perform scan. Print names of installed logs
  --logSilent							Do not add default console log.

  -r,TYPE[,OPTIONS]+
  --report,TYPE[,OPTIONS]+
  --reportInstalled						Do not perform scan. Print names of installed reports

  --batch[,DIR]+						Create a report for each folder in DIR, for each DIR
  										Ignores other options
  --debug								Throw exceptions instead of logging them

Examples:
  php.exe main.php c:/source_code
  php.exe main.php /opt/dev/source_code
  php.exe main.php --pluginsIgnore,FindBugs,PMD,Antic,JLint /opt/dev/source_code
  php.exe main.php --log,ConsoleLog,7 "c:/orange/"
  php.exe main.php --onlyPlugins,BuiltIn C:/example/
EOT;

if ($_SERVER['argc'] < 2){
	print($help);
	exit(0);
}

foreach (
	(new \Yasca\Core\IteratorBuilder)
	->from($_SERVER['argv'])
	//Skip the name of the script file
	->skip(1)
	->selectKeys(static function($arg){
		$options = \str_getcsv($arg);
		$switch = \array_shift($options);
		if ($options === null){
			return [ [], $switch];
		} else {
			return [$options, $switch];
		}
	})

	as $switch => $options
){
	//As of PHP 5.4.3, switch() uses loose comparision instead of strict.
	//Use if/elseif instead.
	if 		 ($switch === '-h' 		||
			  $switch === '--help'
  	){
		print($help);
		exit(0);

	} elseif ($switch === '-v' ||
			  $switch === '--version'
  	){
		print($version);
		exit(0);

	} elseif ($switch === '--pluginInstalled' ||
			  $switch === '--pluginsInstalled'||
			  $switch === '--installedPlugins'
	){
		(new \Yasca\Core\IteratorBuilder)
		->from(\Yasca\Plugin::$installedPlugins)
		->from(static function($plugins){return $plugins;})
		->forAll(static function($plugin){
			print("$plugin\n");
		});
		exit(0);

	} elseif ($switch === '--logInstalled' ||
			  $switch === '--logsInstalled'
  	){
		foreach(\Yasca\Log::getInstalledLogs() as $log){
			print("$log\n");
		}
		exit(0);

	} elseif ($switch === '--reportInstalled' ||
			  $switch === '--reportsInstalled'
	){
		foreach(\Yasca\Report::getInstalledReports() as $report){
			print("$report\n");
		}
		exit(0);


	} elseif ($switch === '--batch'){
		foreach(
			(new \Yasca\Core\IteratorBuilder)
			->from($options)
			->from(static function($scanDir){
				return (new \Yasca\Core\IteratorBuilder)
				->from(new \DirectoryIterator($scanDir))
				->selectKeys(static function($dir) use ($scanDir){
					return [$dir, $scanDir];
				});
			})
			->where(static function($fileinfo){
				return !$fileinfo->isDot() && $fileinfo->isDir();
			})
			->select(static function($fileinfo, $scanDir) use ($scannerOptions){
				static $count = 0;
				$scannerOptions['targetDirectory'] = $fileinfo->getRealpath();
				return (new \Yasca\Scanner($scannerOptions))
				->attachLogObserver(
					new \Yasca\Logs\ConsoleLog([
						null,
						'#' . \str_pad('' . $count++, 3) . '  ',
					])
				)
				->attachResultObserver(
					new \Yasca\Reports\HtmlFileReport(["{$scanDir}\\{$fileinfo->getBasename()}.html"])
				)
				->executeAsync();
			})
			->toList()

			as $async
		){
			$async->getResult();
		}
		exit(0);

	} elseif ($switch === '--debug'){
		$scannerOptions['debug'] = true;

	} elseif ($switch === '--silent' ||
			  $switch === '--logSilent'
  	){
		$addDefaultLog = false;

	} elseif ($switch === '-l' 		||
			  $switch === '--log'
  	){
		$type = '\Yasca\Logs\\' . \array_shift($options);
		$logs->enqueue(new $type($options));
		$addDefaultLog = false;

	} elseif ($switch === '-r' ||
			  $switch === '--report'
  	){
		$type = '\Yasca\Reports\\' . \array_shift($options);
		$reports->enqueue(new $type($options));
		$addDefaultReport = false;

	} elseif ($switch === '--logOption' ||
			  $switch === '--logOptions'
  	){
		$type = '\Yasca\Logs\\' . \array_shift($options);
		print($type::OPTIONS);
		exit(0);

	} elseif ($switch === '--reportOption' ||
			  $switch === '--reportOptions'
  	){
		$type = '\Yasca\Reports\\' . \array_shift($options);
		print($type::OPTIONS);
		exit(0);

	} elseif ($switch === '--ignoredPlugin'  ||
			  $switch === '--ignoredPlugins' ||
		 	  $switch === '--ignorePlugin'	 ||
			  $switch === '--ingorePlugins'	 ||
			  $switch === '--pluginIgnored'	 ||
			  $switch === '--pluginsIgnored' ||
			  $switch === '--pluginIgnore'	 ||
			  $switch === '--pluginsIgnore'
	){
		$scannerOptions['pluginsIgnore'] = $options;

	} elseif ($switch === '--onlyPlugins' ||
			  $switch === '--onlyPlugin'  ||
			  $switch === '--pluginsOnly' ||
			  $switch === '--pluginOnly'
	){
		$scannerOptions['pluginsOnly'] = $options;

	} elseif ($switch === '--onlyExtension' ||
			  $switch === '--onlyExtensions'||
			  $switch === '--extensionOnly' ||
			  $switch === '--extensionsOnly'
	){
		$scannerOptions['extensionsOnly'] = $options;

	} elseif ($switch === '--ignoreExtension' ||
			  $switch === '--ignoreExtensions'||
			  $switch === '--extensionIgnore' ||
			  $switch === '--extensionsIgnore'
	){
		$scannerOptions['extensionsIgnore'] = $options;

	} else {
		$scannerOptions['targetDirectory'] = $switch;
	}
}

if ($addDefaultLog === true){
	$logs->enqueue(new \Yasca\Logs\ConsoleLog);
}
if ($addDefaultReport === true){
	$reports->enqueue(new \Yasca\Reports\HtmlFileReport(['report.html']));
}

$scanner = new \Yasca\Scanner($scannerOptions);
foreach($logs as $log){ $scanner->attachLogObserver($log);}
foreach($reports as $report){ $scanner->attachResultObserver($report);}
//Allow everything but the scanner (and things the scanner holds) to drop out of scope
return $scanner;
})->execute();